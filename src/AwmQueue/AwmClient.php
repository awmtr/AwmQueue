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

require_once('AwmAbstract.php');

class AwmClient extends AwmAbstract
{
    
    const DEFAULT_CLIENT_TIMEOUT = 2000;
    
    const PRIORITY_HIGH   = 1;
    const PRIORITY_NORMAL = 2;
    const PRIORITY_LOW    = 3;
    
    const STORE_TYPE_DIRECT   = 1;
    const STORE_TYPE_SEQUENCE = 2;
    const STORE_TYPE_HOLDER   = 3;
    
    const ERROR_TASK_NOT_ALLOWED_PRIORITY   = 1;
    const ERROR_TASK_NOT_ALLOWED_TASK       = 2;
    const ERROR_TASK_BAD_EXCEPTION          = 3;
    const ERROR_TASK_SERVER_DOWN            = 4;
    const ERROR_TASK_SEQUENCE               = 5;
    const ERROR_TASK_NOT_ALLOWED_STORE_TYPE = 6;
    const ERROR_TASK_HOLDER                 = 7;
    
    protected $_client = null;
    
    protected $_tasksHolder = null;
    
    /**
     * Get one or all priorities
     *
     * @param int $priority - optional
     * 
     * @return bool|array
     */
    public static function getPriorities($priority = null)
    {
        $priorities = [
            static::PRIORITY_HIGH   => 'high',
            static::PRIORITY_NORMAL => 'normal',
            static::PRIORITY_LOW    => 'low'
        ];
        
        return $priority === null ? $priorities : (isset($priorities[$priority]) ? $priorities[$priority] : false);
    }
    
    /**
     * Get one or all store types
     *
     * @param int $storeType - optional
     * 
     * @return bool|array
     */
    public static function getStoreTypes($storeType = null)
    {
        $storeTypes = [
            static::STORE_TYPE_DIRECT   => 'direct',
            static::STORE_TYPE_SEQUENCE => 'sequence',
            static::STORE_TYPE_HOLDER   => 'holder'
        ];
        
        return $storeType === null ? $storeTypes : (isset($storeTypes[$storeType]) ? $storeTypes[$storeType] : false);
    }
    
    /**
     * Init connection to client
     * 
     * @param bool $skipNotify - option
     * 
     * @return AwmClient
     */
    protected function initConnection($skipNotify = false, $timeout = null)
    {
        try {
            if (!$this->_client) {
                if ($this->_config === null) {
                    $this->setConfig([]);
                }
                $this->_client = new \GearmanClient();
                $this->_client->addServers($this->_config['servers']);
                
                if (!is_int($timeout)) {
                    if (!empty($this->_config['client_timeout']) && is_int($this->_config['client_timeout'])) {
                        $timeout = $this->_config['client_timeout'];
                    } else {
                        $timeout = static::DEFAULT_CLIENT_TIMEOUT;
                    }
                }
                
                $this->_client->setTimeout($timeout);
            }
            
            \ob_start();
            if (method_exists($this->_client, 'ping')) {
                $res = $this->_client->ping('data for testing');
            } else {
                $res = $this->_client->echo('data for testing');
            }
            $notice = \ob_get_clean();
            if (!$res) {
                throw new AwmException(\trim('Clients are not reachable. ' . $notice));
            }
        } catch (\Exception $e) {
            $error = "AwmClient ERROR: {$e->getMessage()}";
            $this->setErrorMessage($error);
            if (!$skipNotify) {
                $this->_notifyAboutError($error);
            }
            throw new AwmException($error);
        }
        
        return $this;
    }
    
    /**
     * Create task
     *
     * @param string $callTask
     * @param mixed $params
     * @param int $priority - optional
     * @param Closure $callBack - optional
     * 
     * @return array
     */
    public function createTask($callTask, $params, $priority = self::PRIORITY_NORMAL, Closure $callBack = null)
    {
        if (($method = static::getPriorities($priority)) === false) {
            return ['status' => false, 'error' => ['code' => static::ERROR_TASK_NOT_ALLOWED_PRIORITY, 'message' => "Priority '{$priority}' is not in allowed priorities list"]];
        }
        
        if (!$this->getAllowedTasks($callTask)) {
            return ['status' => false, 'error' => ['code' => static::ERROR_TASK_NOT_ALLOWED_TASK, 'message' => "Method '{$callTask}' is not in allowed methods list"]];
        }
        
        try {
            $this->initConnection();
        } catch (\Exception $e) {
            return ['status' => false, 'error' => ['code' => static::ERROR_TASK_SERVER_DOWN, 'message' => '[TASK => ' . $callTask . '] We have problems with servers. ' . $e->getMessage()]];
        }
        
        if (!($callBack instanceof \Closure)) {
            $callBack = function(\GearmanClient $client, $returnCode, $result) {
                switch ($returnCode) {
                    case GEARMAN_WORK_DATA:
                        //\error_log(sprintf('Data: %s', var_export($result, true)));
                        
                        return false;
                    case GEARMAN_WORK_STATUS:
                        list ($numerator, $denominator) = $client->doStatus();
                        \error_log("Status: {$numerator}/{$denominator} complete");
                        
                        return false;
                    case GEARMAN_WORK_FAIL:
                        \error_log('Operation fail');
                        
                        return true;
                    case GEARMAN_SUCCESS:
                        //\error_log('Operation success');
                        
                        return false;
                    default:
                        \error_log("Return code: {$client->returnCode()}");
                        \error_log("Error: {$client->error()}");
                        \error_log("Error number: {$client->getErrno()}");
                        
                        return true;
                }
            };
        }
        
        $method = \ucfirst($method);
        
        $params = ['config' => $this->_config, 'params' => $params, 'background' => false];
        
        try {
            do {
                if (\strtolower($method) == 'normal' && !\method_exists($this->_client, "do{$method}")) {
                    $method = '';
                }
                $result = unserialize($this->_client->{"do{$method}"}($callTask, serialize($params)));
                if ($callBack($this->_client, $this->_client->returnCode(), $result)) {
                    break;
                }
            } while ($this->_client->returnCode() != GEARMAN_SUCCESS);
            
            if (!in_array($this->_client->returnCode(), [GEARMAN_SUCCESS, GEARMAN_WORK_STATUS, GEARMAN_WORK_DATA])) {
                throw new AwmException('Bad return code', $this->_client->returnCode());
            }
        } catch (\Exception $e) {
            return ['status' => false, 'error' => ['code' => static::ERROR_TASK_BAD_EXCEPTION, 'message' => $e->getMessage()]];
        }
        
        return ['status' => true, 'result' => $result];
    }
    
    /**
     * Create background task
     *
     * @param string $callTask
     * @param mixed $params
     * @param int $priority - optional
     * @param int $storeType - optional Store background task by selected type
     * 
     * @return array
     */
    public function createBackgroundTask($callTask, $params, $priority = self::PRIORITY_NORMAL, $storeType = self::STORE_TYPE_DIRECT)
    {
        if (($method = static::getPriorities($priority)) === false) {
            return ['status' => false, 'error' => ['code' => static::ERROR_TASK_NOT_ALLOWED_PRIORITY, 'message' => "Priority '{$priority}' is not in allowed priorities list"]];
        }
        
        if (!$this->getAllowedTasks($callTask)) {
            return ['status' => false, 'error' => ['code' => static::ERROR_TASK_NOT_ALLOWED_TASK, 'message' => "Task '{$callTask}' is not in allowed methods list"]];
        }
        
        if (($store = static::getStoreTypes($storeType)) === false) {
            return ['status' => false, 'error' => ['code' => static::ERROR_TASK_NOT_ALLOWED_STORE_TYPE, 'message' => "Store type '{$storeType}' is not in allowed store types list"]];
        }
        
        if ($store == 'sequence') {
            if (!$this->isSequenceAvailable()) {
                return ['status' => false, 'error' => ['code' => static::ERROR_TASK_NOT_ALLOWED_STORE_TYPE, 'message' => "Store type '{$storeType}' was not configured"]];
            }
            
            try {
                $config  = $this->getSequenceConfig();
                $storage = Sequence\FactorySequence::create($config['driver'], $config);
            } catch (\Exception $e) {
                return ['status' => false, 'error' => ['code' => static::ERROR_TASK_SEQUENCE, 'message' => '[TASK => ' . $callTask . '] We have problems with sequence storage. ' . $e->getMessage()]];
            }
        } else if ($store == 'holder') {
            try {
                if (!($this->_tasksHolder instanceof Queue\HolderTask)) {
                    $this->_tasksHolder = new Queue\HolderTask;
                }
                if (!($holderKey = $this->_tasksHolder->attachWithKey(new Queue\HolderTaskObject($callTask, $params, $priority)))) {
                    throw new AwmException('Unable attach object');
                }
                
                return ['status' => true, 'job_handle' => false, 'store_key' => false, 'holder_key' => $holderKey];
            } catch (\Exception $e) {
                return ['status' => false, 'error' => ['code' => static::ERROR_TASK_HOLDER, 'message' => $e->getMessage()]];
            }
        } else {
            try {
                $this->initConnection();
            } catch (\Exception $e) {
                return ['status' => false, 'error' => ['code' => static::ERROR_TASK_SERVER_DOWN, 'message' => '[TASK => ' . $callTask . '] We have problems with servers. ' . $e->getMessage()]];
            }
        }
        
        if ($store == 'sequence') {
            try {
                if (!($key = $storage->store(['task' => $callTask, 'params' => $params, 'priority' => $priority, 'source' => $this->_config['environment'] . '|' . $this->_config['servers']]))) {
                    throw new AwmException('Unable store task into storage');
                }
                unset($storage);
                
                $res = ['status' => true, 'job_handle' => false, 'store_key' => $key, 'holder_key' => false];
            } catch (\Exception $e) {
                $res = ['status' => false, 'error' => ['code' => static::ERROR_TASK_SEQUENCE, 'message' => $e->getMessage()]];
            }
        } else {
            $method = $method == 'normal' ? '' : \ucfirst($method);
            
            $params = ['config' => $this->_config, 'params' => $params, 'background' => true];
            
            try {
                $jobHandle = $this->_client->{"do{$method}Background"}($callTask, \serialize($params));
                if ($this->_client->returnCode() != GEARMAN_SUCCESS) {
                    throw new AwmException('Bad return code', $this->_client->returnCode());
                }
                
                $res = ['status' => true, 'job_handle' => $jobHandle, 'store_key' => false, 'holder_key' => false];
            } catch (\Exception $e) {
                $res = ['status' => false, 'error' => ['code' => static::ERROR_TASK_BAD_EXCEPTION, 'message' => $e->getMessage()]];
            }
        }
        
        return $res;
    }
    
    /**
     * Create direct background task. This is alias for static::createBackgroundTask
     *
     * @param string $callTask
     * @param mixed $params
     * @param int $priority - optional
     * 
     * @return array
     */
    public function createBackgroundTaskDirect($callTask, $params, $priority = self::PRIORITY_NORMAL)
    {
        return $this->createBackgroundTask($callTask, $params, $priority, static::STORE_TYPE_DIRECT);
    }
    
    /**
     * Create sequence background task. This is alias for static::createBackgroundTask
     *
     * @param string $callTask
     * @param mixed $params
     * @param int $priority - optional
     * 
     * @return array
     */
    public function createBackgroundTaskSequence($callTask, $params, $priority = self::PRIORITY_NORMAL)
    {
        return $this->createBackgroundTask($callTask, $params, $priority, static::STORE_TYPE_SEQUENCE);
    }
    
    /**
     * Create holder background task. This is alias for static::createBackgroundTask
     *
     * @param string $callTask
     * @param mixed $params
     * @param int $priority - optional
     * 
     * @return array
     */
    public function createBackgroundTaskHolder($callTask, $params, $priority = self::PRIORITY_NORMAL)
    {
        return $this->createBackgroundTask($callTask, $params, $priority, static::STORE_TYPE_HOLDER);
    }
    
    /**
     * Send all hold background tasks to Gearman
     * 
     * @return bool
     */
    public function sendHoldBackgroundTasks()
    {
        $errors = [];
        if ($this->_tasksHolder instanceof Queue\HolderTask && $this->_tasksHolder->count()) {
            foreach ($this->_tasksHolder->groupByTaskName() as $taskName => $data) {
                foreach ($data as $priority => $params) {
                    $res = $this->createBackgroundTaskDirect($taskName, $params, $priority);
                    if (!$res['status']) {
                        $errors[$taskName][$priority][] = $res['error'];
                    }
                }
            }
        }
        
        return !empty($errors) ? $errors : true;
    }
    
    /**
     * Check config data for sequence availability
     * 
     * @return bool
     */
    public function isSequenceAvailable()
    {
        return !empty($this->_config['sequence']);
    }
    
    /**
     * Get sequence config data
     * 
     * @return array
     */
    public function getSequenceConfig()
    {
        $config = [];
        if ($this->isSequenceAvailable()) {
            global $db;
            
            $config = $this->_config['sequence'];
            
            if (stripos($config['driver'], 'mysql') !== false) {
                foreach (['host', 'user', 'password', 'database', 'port'] as $key) {
                    $dbKey = in_array($key, ['host', 'user']) ? $key . 'name' : $key;
                    if (!isset($config[$key]) && isset($db['default'][$dbKey])) {
                        $config[$key] = $db['default'][$dbKey];
                    }
                }
            }
        }
        
        return $config;
    }
    
    /**
     * Get job status by job handle
     * 
     * @param string $jobHandle
     * 
     * @return false|array
     */
    public function getJobStatus($jobHandle)
    {
        try {
            $this->initConnection();
        } catch (\Exception $e) {
            return false;
        }
        
        return $this->_client->jobStatus($jobHandle);
    }
    
    /**
     * Ping all servers
     * 
     * @return bool
     */
    public function ping()
    {
        $res = true;
        try {
            $this->initConnection(true);
        } catch (\Exception $e) {
            $res = false;
        }
        
        return $res;
    }
    
    /**
     * Run function before destroy object
     *
     * @return void
     */
    final public function __destruct()
    {
        $this->sendHoldBackgroundTasks();
    }
    
}
