<?php

/**
 * Copyright 2017 Awm Team
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace AwmQueue\Sequence\Drivers;

final class Memcached extends \AwmQueue\Sequence\AbstractSequence
{
    
    /**
     * Prefix data
     *
     * @var string
     */
    protected $_prefix = '';
    
    /**
     * Expiration time for store method in seconds
     *
     * @var int
     */
    protected $_storeExpire = 3600;
    
    /**
     * Get number of data in data storage
     *
     * @return int
     */
    public function count()
    {
        return count($this->_keysList());
    }
    
    /**
     * Read all data from data storage
     *
     * @param int $limit - optional
     * 
     * @return mixed
     */
    public function fetchAll($limit = false)
    {
        $limit = (int) $limit;
        $count = 0;
        $list  = $this->_keysList();
        $data  = [];
        
        foreach ($list as $key) {
            if ($row = $this->_exec('get', [$key])['result']) {
                $data[] = $row;
                $count++;
                if ($limit > 0 && $limit <= $count) {
                    break;
                }
            }
        }
        
        if ($data) {
            usort($data, [$this, '_sortByPriority']);
        }
        
        return $data;
    }
    
    /**
     * Read data from data storage
     *
     * @param string $key
     * 
     * @return mixed
     */
    public function fetch($key)
    {
        return $this->_exec('get', [$this->_prefix . $key])['result'];
    }
    
    /**
     * Store data from data storage
     *
     * @param array $data
     * @param string $key - optional
     * 
     * @return mixed
     */
    public function store(array $data, $key = null)
    {
        if (empty($data) || empty($data['task']) || empty($data['priority']) || empty($data['params']) || empty($data['source'])) {
            return false;
        }
        
        if (empty($key)) {
            if (!($key = (int) $this->_exec('get', [$this->_prefix . 'counter'])['result'])) {
                $key = 1;
                if (!$this->_exec('set', [$this->_prefix . 'counter', $key, 0, $this->_storeExpire * 24])['status']) {
                    return false;
                }
            }
            if ($this->_exec('set', [$this->_prefix . $key, $data, 0, $this->_storeExpire])['status']) {
                $this->_exec('increment', [$this->_prefix . 'counter']);
            } else {
                return false;
            }
        } else {
            if (!$this->_exec('replace', [$this->_prefix . $key, $data, 0, $this->_storeExpire])['status']) {
                return false;
            }
        }
        
        return $key;
    }
    
    /**
     * Remove data from data storage
     *
     * @param string $key
     * 
     * @return mixed
     */
    public function remove($key)
    {
        return $this->_exec('delete', [$this->_prefix . $key, 0])['status'];
    }
    
    /**
     * Remove all data from data storage
     *
     * @return mixed
     */
    public function removeAll()
    {
        return $this->_exec('flush')['status'];
    }
    
    /**
     * Set data from config
     *
     * @param array $config
     * 
     * @return \AbstractSequence
     */
    public function setConfig(array $config)
    {
        $res = parent::setConfig($config);
        
        if (empty($this->_prefix)) {
            $this->_prefix = !empty($this->_config['prefix']) ? $this->_config['prefix'] : $this->_config['table'] . '_';
        }
        $this->_prefix = (string) $this->_prefix;
        
        return $res;
    }

    /**
     * Sort fetched data by priority
     *
     * @return bool
     */
    protected function _sortByPriority(array $a, array $b)
    {
        return $a['priority'] - $b['priority'];
    }
    
    /**
     * Get list of the keys in data storage
     *
     * @return array
     */
    protected function _keysList()
    {
        $connection = $this->_getConnection()->_connection;
        
        $allSlabs = $connection->getExtendedStats('slabs');
        $items    = $connection->getExtendedStats('items');
        $pattern  = '/^' . $this->_prefix . '(?!counter)/';
        $list     = [];
        
        foreach ($allSlabs as $server => $slabs) {
            foreach ($slabs as $slabId => $slabMeta) {
                if (!is_int($slabId)) {
                    continue;
                }
                $cdump = $connection->getExtendedStats('cachedump', (int) $slabId);
                foreach ($cdump as $keys => $arrVal) {
                    if (!is_array($arrVal)) {
                        continue;
                    }
                    foreach ($arrVal as $k => $v) {
                        if (preg_match($pattern, $k)) {
                            $list[] = $k;
                        }
                    }
                }
            }
        }
        
        return $list;
    }
    
    /**
     * Execute command
     *
     * @param string $command
     * @param array $args - optional
     * 
     * @return mixed
     */
    protected function _exec($command, array $args = null)
    {
        if ($args === null) {
            $res = call_user_func([$this->_getConnection()->_connection, $command]);
        } else {
            $res = call_user_func_array([$this->_getConnection()->_connection, $command], $args);
        }
        
        if (!empty($this->_config['debug'])) {
            $this->_debug(
                sprintf(
                    '[STATUS => "%s"%s] %s',
                    $res !== false ? 'OK' : 'ERROR',
                    $res !== false ? '' : ' ERROR: "' . $command .  '"',
                    "\n\nExecuted command => {$command}" . (!empty($args) ? "\n\nParams =>\n" . var_export($args, true) : '')
                )
            );
        }
        
        return ['status' => (bool) $res, 'result' => $res];
    }
    
    /**
     * Get connection to data storage
     * 
     * @return \AwmQueue\Sequence\AbstractSequence
     */
    protected function _getConnection()
    {
        if (!($this->_connection instanceof \Memcache)) {
            try {
                if (!extension_loaded('memcache')) {
                    throw new ExceptionSequence('Extension "memcache" is not loaded');
                }
                $this->_connection = new \Memcache;
                if (!$this->_connection->connect($this->_config['host'], !empty($this->_config['port']) ? $this->_config['port'] : 11211, true)) {
                    throw new ExceptionSequence('Problems with connection to memcache on ' . $this->_config['host'] . ':' . (!empty($this->_config['port']) ? $this->_config['port'] : 11211));
                }
            } catch (ExceptionSequence $e) {
                throw new ExceptionSequence('Connect failed: ' . $e->getMessage());
            }
        }
        
        return $this;
    }
    
    /**
     * Close connection to data storage
     * 
     * @return \AwmQueue\Sequence\AbstractSequence
     */
    protected function _closeConnection()
    {
        if ($this->_connection instanceof \Memcache) {
            $this->_connection->close();
            $this->_connection = null;
        }
        
        return $this;
    }
    
}
