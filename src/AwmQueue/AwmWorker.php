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

class AwmWorker extends AwmAbstract
{
    
    protected $_worker = null;
    
    /**
     * Init connection to worker
     * 
     * @param bool $skipNotify - option
     * 
     * @return Worker
     */
    protected function initConnection($skipNotify = false)
    {
        if (!$this->_worker) {
            if ($this->_config === null) {
                $this->setConfig([]);
            }
            try {
                $this->_worker = new \GearmanWorker();
                $this->_worker->addServers($this->_config['servers']);
                
                \ob_start();
                $res = $this->_worker->echo('data for testing');
                $notice = \ob_get_clean();
                
                if (!$res) {
                    throw new AwmException('Workers are not reachable. [' . $this->_config['servers'] . '] ' . $notice);
                }
            } catch (\Exception $e) {
                $error = "AwmWorker ERROR: {$e->getMessage()}";
                $this->setErrorMessage($error);
                if (!$skipNotify) {
                    $this->_notifyAboutError($error);
                }
                throw new AwmException($error);
            }
        }
        
        return $this;
    }
    
    /**
     * Run worker
     * 
     * @return void
     */
    public function run()
    {
        $this->initConnection();
        
        $excludeTask  = !empty($this->_config['excluded_tasks']) && is_array($this->_config['excluded_tasks']) ? $this->_config['excluded_tasks'] : [];
        $singleWorker = !empty($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : null;
        $emptyWorker  = true;
        
        foreach ($this->getAllowedTasks() as $alias => $task) {
            if ($singleWorker) {
                if ($singleWorker == $task) {
                    $this->_worker->addFunction($alias, [Queue\FactoryTask::create($task, $this->_config['tasks_folder_path']), 'execute'], $this);
                     $emptyWorker = false;
                }
            } else if (!in_array($task, $excludeTask)) {
                $this->_worker->addFunction($alias, [Queue\FactoryTask::create($task, $this->_config['tasks_folder_path']), 'execute'], $this);
                $emptyWorker = false;
            }
        }
        
        if ($emptyWorker) {
            $this->setErrorMessage('Found empty "' . $this->_config['environment'] . '" worker for ' . ($singleWorker ? $singleWorker . ' task' : 'all tasks') . ' ...');
            sleep(1);
            exit(0);
        }
        
        $this->setInfoMessage('Up "' . $this->_config['environment'] . '"' . ($singleWorker ? ' single ' . $singleWorker : '') . ' worker ...');
        
        while (true) {
            $this->setDebugMessage('Worker "' . $this->_config['environment'] . '" in progress ...');
            
            $this->_worker->work();
            
            $this->setDebugMessage(sprintf('Worker "' . $this->_config['environment'] . '" got code: "%s"', $this->_worker->returnCode()));
            if ($this->_worker->returnCode() != GEARMAN_SUCCESS) {
                $this->setErrorMessage('Restart "' . $this->_config['environment'] . '"' . ($singleWorker ? ' single ' . $singleWorker : '') . ' worker ...');
                break;
            }
        }
        
        $this->setInfoMessage('Halt "' . $this->_config['environment'] . '"' . ($singleWorker ? ' single ' . $singleWorker : '') . ' worker ...');
    }
        
}
