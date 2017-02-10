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

final class Redis extends \AwmQueue\Sequence\AbstractSequence
{
    
    const KEY_COUNTER = 'counter';
    const KEY_LIST    = 'storelist';
    const KEY_PREFIX  = 'job_';
    
    /**
     * Expiration time for store method in seconds
     *
     * @var int
     */
    protected $_storeExpire = 3600;
    
    /**
     * Index db
     *
     * @var int
     */
    protected $_indexDb = 0;
    
    /**
     * Name space
     *
     * @var string
     */
    protected $_nameSpace = '';
    
    /**
     * Get number of data in data storage
     *
     * @return int
     */
    public function count()
    {
        /*
        $res = $this->_exec('keys', ['[0-9]*']);
        
        return $res['status'] ? count($res['result']) : 0;
        */
        
        /*
        $count = 0;
        $res   = $this->_exec('dbsize');
        if ($res['status']) {
            $count = $res['result'];
        }
        
        if ($count > 0 && $this->_exec('get', [static::KEY_COUNTER])['result']) {
            $count--;
        }
        
        if ($count > 0 && $this->_exec('lLen', [static::KEY_LIST])['result']) {
            $count--;
        }
        
        return $count;
        */
        
        return $this->_exec('lLen', [static::KEY_LIST])['result'];
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
        $params = [
            'sort' => 'asc',
            'by'   => $this->_nameSpace . 'job_*->priority',
            'get'  => []
        ];
        
        //$params['alpha'] = false;
        if (($limit = (int) $limit) > 0) {
            $params['limit'] = [0, $limit];
        }
        
        $fields = ['id', 'task', 'priority', 'params', 'source'];
        foreach ($fields as $field) {
            $params['get'][] = sprintf('%sjob_*->%s', $this->_nameSpace, $field);
        }
        
        $res = $this->_exec('sort', [static::KEY_LIST, $params]);
        if ($res['status']) {
            $arr = array_chunk($res['result'], count($fields));
            $res = [];
            foreach ($arr as $chunk) {
                $r = array_combine($fields, $chunk);
                $r['params'] = unserialize($r['params']);
                $res[] = $r;
            }
        } else {
            $res = [];
        }
        
        return $res;
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
        $row = $this->_exec('hGetAll', [static::KEY_PREFIX . $key])['result'];
        
        if (!empty($row['params'])) {
            $row['params'] = unserialize($row['params']);
        }
        
        return $row;
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
        
        $data['params'] = serialize($data['params']);
        
        $list = [static::KEY_LIST];
        if (empty($key)) {
            if (!($key = (int) $this->_exec('get', [static::KEY_COUNTER])['result'])) {
                $key = 1;
                if (!$this->_exec('set', [static::KEY_COUNTER, $key])['status'] || !$this->_exec('expire', [static::KEY_COUNTER, $this->_storeExpire * 24])['status']) {
                    return false;
                }
            }
            $storeKey   = static::KEY_PREFIX . $key;
            $list[]     = $key;
            $data['id'] = $key;
            if (!$this->_exec('hMset', [$storeKey, $data])['status'] || !$this->_exec('expire', [$storeKey, $this->_storeExpire])['status']
                || !$this->_exec('rPush', $list)['status'] || !$this->_exec('expire', [$list[0], $this->_storeExpire])['status']
                    || !$this->_exec('incr', [static::KEY_COUNTER])['status'])
            {
                return false;
            }
        } else {
            $storeKey   = static::KEY_PREFIX . $key;
            $list[]     = $key;
            $data['id'] = $key;
            
            $this->_exec('lRem', [$list[0], $key, 0]);
            
            if ($this->_exec('hMset', [$storeKey, $data])['status'] || !$this->_exec('expire', [$storeKey, $this->_storeExpire])['status']
                || !$this->_exec('rPush', $list)['status'] || !$this->_exec('expire', [$list[0], $this->_storeExpire])['status'])
            {
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
        if ($this->_exec('del', [static::KEY_PREFIX . $key])['status']) {
            return $this->_exec('lRem', [static::KEY_LIST, $key, 0])['status'];
        }
        
        return false;
    }
    
    /**
     * Remove all data from data storage
     *
     * @return mixed
     */
    public function removeAll()
    {
        return $this->_exec('flushDb')['status'];
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
        
        if (empty($this->_nameSpace)) {
            $this->_nameSpace = !empty($this->_config['name_space']) ? $this->_config['name_space'] : $this->_config['table'];
        }
        $this->_nameSpace = (string) $this->_nameSpace;
        
        return $res;
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
        if (!($this->_connection instanceof \Redis)) {
            try {
                if (!extension_loaded('redis')) {
                    throw new ExceptionSequence('Extension "redis" is not loaded');
                }
                $this->_connection = new \Redis;
                if (!$this->_connection->connect($this->_config['host'], !empty($this->_config['port']) ? $this->_config['port'] : 6379, true)) {
                    throw new ExceptionSequence('Problems with connection to redis on ' . $this->_config['host'] . ':' . (!empty($this->_config['port']) ? $this->_config['port'] : 6379));
                }
                $this->_connection->select((int) $this->_indexDb);
                $this->_connection->setOption(\Redis::OPT_PREFIX, $this->_nameSpace);
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
        if ($this->_connection instanceof \Redis) {
            $this->_connection->close();
            $this->_connection = null;
        }
        
        return $this;
    }
    
}
