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

namespace AwmQueue;

abstract class AwmAbstract
{
    
    const DEFAULT_SERVER_IP   = '127.0.0.1';
    const DEFAULT_SERVER_PORT = 4730;
    
    const MESSAGE_ERROR = 1;
    const MESSAGE_INFO  = 2;
    const MESSAGE_DEBUG = 4;
    
    protected $_config = null;
    
    protected static $_instance = [];
    
    /**
     * We need override this method
     */
    abstract protected function initConnection();
    
    /**
     * Get instance of the class
     *
     * @param string $prefix - optional
     * 
     * @return object
     */
    public static function getInstance($prefix = null)
    {
        $class = $prefix . get_called_class();
        if (!isset(static::$_instance[$class])) {
            static::$_instance[$class] = new static;
        }
        
        return static::$_instance[$class];
    }
    
    /**
     * Reset instance of the class
     * 
     * @param string $prefix - optional
     * 
     * @return void
     */
    public static function resetInstance($prefix = null)
    {
        $class = $prefix . get_called_class();
        
        static::$_instance[$class] = null;
    }
    
    /**
     * Check if system ready to use task queue
     * 
     * @return bool
     */
    public function isReadyToUse()
    {
        return \extension_loaded('gearman') && !empty($this->_config['ready_to_use']);
    }
    
    /**
     * Get config data
     *
     * @param array
     */
    public function getConfig()
    {
        return $this->_config;
    }
    
    /**
     * Setup config data
     *
     * @param array $config
     * 
     * @return AwmAbstract
     */
    public function setConfig(array $config)
    {
        if (!empty($config['storage'])) {
            $config['storage'] = str_ireplace('{environment}', $config['environment'], $config['storage']);
        }
        
        if (!empty($config['servers']) && is_array($config['servers'])) {
            $servers = [];
            foreach ($config['servers'] as $server) {
                $servers[] = "{$server['host']}:{$server['port']}";
            }
            $config['servers'] = join(',', $servers);
        } else {
            $config['servers'] = static::DEFAULT_SERVER_PORT . ':' . static::DEFAULT_SERVER_PORT;
        }
        
        $this->_config = $config;
        
        return $this;
    }
    
    /**
     * Get allowed tasks
     *
     * @param string $callTask - optional
     * 
     * @return bool|array
     */
    public function getAllowedTasks($callTask = null)
    {
        return Queue\FactoryTask::getAllowedTasks($callTask, $this->_config['tasks_folder_path']);
    }
    
    /**
     * Using for messages
     * 
     * @throws AwmException
     * 
     * @return bool|string
     */
    public function __call($method, $args)
    {
        if (stripos($method, 'set') !== false && preg_match('/^set(Info|Error|Debug)Message$/i', $method, $match)) {
            $level = strtoupper($match[1]);
            if (!isset($args[0]) || !defined('static::MESSAGE_' . $level)) {
                return false;
            }
            
        	$args[0] = (string) $args[0];
        	if ($args[0] !== '' && $this->_config['logger_level'] & constant('static::MESSAGE_' . $level)) {
            	if (count($args) > 1) {
            	    $args[0] = call_user_func_array('sprintf', $args);
            	}
            	
            	call_user_func($this->_config['logger_callback'], $args[0]);
            	
            	if (!empty($this->_config['debug'])) {
            	    echo $args[0] . PHP_EOL;
            	}
            	
            	return stripslashes($args[0]);
        	}
        	
        	return false;
        }
        
        throw new AwmException('Unknown method was called => ' . $method);
    }
    
    /**
     * Register autoload
     * 
     * @return void
     */
    public static function autoloadRegister()
    {
        spl_autoload_register(function($class) {
            $dir  = dirname(__FILE__);
            $path = explode('\\', $class);
            $path = array_slice($path, 1);
            
            require_once($dir . DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $path) . '.php');
        });
    }
    
    /**
     * Notify about error
     *
     * @param string $error
     * 
     * @return void
     */
    protected function _notifyAboutError($error)
    {
        if (!empty($this->_config['notify_error_callback'])) {
            call_user_func($this->_config['notify_error_callback'], $error);
        } else {
            $this->setErrorMessage('AwmAbstract::notifyAboutError => ' . $error);
            
            throw new AwmException('AwmAbstract::notifyAboutError => ' . $error);
        }
    }
    
}

AwmAbstract::autoloadRegister();
