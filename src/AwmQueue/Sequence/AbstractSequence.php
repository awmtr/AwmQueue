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

namespace AwmQueue\Sequence;

abstract class AbstractSequence
{
    
    /**
     * Config data
     *
     * @var array
     */
    protected $_config = [];
    
    /**
     * Debug data
     *
     * @var array
     */
    protected $_debug = [];
    
    /**
     * Connection resource
     *
     * @var mixed
     */
    protected $_connection = null;
    
    /**
     * Set config data
     *
     * @param array $config
     *
     * @return void
     */
    final public function __construct(array $config)
    {
        $this->setConfig($config);
    }
    
    /**
     * Close connection after use
     *
     * @return void
     */
    final public function __destruct()
    {
        $this->_closeConnection();
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
        $data = [];
        foreach (['host', 'user', 'password', 'database', 'table'] as $key) {
            if (isset($config[$key])) {
                $data[$key] = $config[$key];
            } else {
                throw new ExceptionSequence('Empty config param "' . $key . '"');
            }
        }
        
        if (isset($config['port'])) {
            $data['port'] = $config['port'];
        }
        
        $data['debug'] = !empty($config['debug']);
        
        $this->_config = $data;
        
        return $this;
    }
    
    /**
     * Get config data
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->_config;
    }
    
    /**
     * Get debug data
     *
     * @return array
     */
    public function getDebug()
    {
        return $this->_debug;
    }
    
    /**
     * Check if data present in data storage
     *
     * @return bool
     */
    public function hasData()
    {
        return $this->count() > 0;
    }
    
    /**
     * Get number of data in data storage
     *
     * @return int
     */
    abstract public function count();
    
    /**
     * Read all data from data storage
     *
     * @param int $limit - optional
     * 
     * @return mixed
     */
    abstract public function fetchAll($limit = false);
    
    /**
     * Read data from data storage
     *
     * @param string $key
     * 
     * @return mixed
     */
    abstract public function fetch($key);
    
    /**
     * Store data from data storage
     *
     * @param array $data
     * @param string $key - optional
     * 
     * @return mixed
     */
    abstract public function store(array $data, $key = null);
    
    /**
     * Remove data from data storage
     *
     * @param string $key
     * 
     * @return mixed
     */
    abstract public function remove($key);
    
    /**
     * Remove all data from data storage
     *
     * @return mixed
     */
    abstract public function removeAll();
    
    /**
     * Get connection to data storage
     * 
     * @return \AwmQueue\Sequence\AbstractSequence
     */
    abstract protected function _getConnection();
    
    /**
     * Close connection to data storage
     * 
     * @return \AwmQueue\Sequence\AbstractSequence
     */
    abstract protected function _closeConnection();
    
    /**
     * Store debug info into debug
     *
     * @param string $debug
     * 
     * @return void
     */
    protected function _debug($debug)
    {
        $this->_debug[] = $debug;
    }
    
}
