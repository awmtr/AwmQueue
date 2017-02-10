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

require_once '../src/AwmQueue/AwmClient.php';
require_once '../src/config.php';

class AwmQueueSequence
{
    
    const FETCH_LIMIT = 100;
    const LAZY_SLEEP  = 1000000; // mc sec.
    
    public function run()
    {
        $awm = $this->_initAwmQueue();
        if ($tasks = $this->_initAwmQueueSequence()->fetchAll(static::FETCH_LIMIT)) {
            foreach ($tasks as $task) {
                $info = $awm->createBackgroundTaskDirect($task['task'], $task['params'], $task['priority']);
                if (!$info['status']) {
                    echo $info['error']['message'] . "\n";
                } else {
                    $this->_initAwmQueueSequence()->remove($task['id']);
                }
            }
        } else {
           usleep(static::LAZY_SLEEP);
        }
    }
    
    public function truncate()
    {
        return $this->_initAwmQueueSequence()->removeAll();
    }
    
    public function loadTasks()
    {
        $params = ['a' => time() + 50];
        
        $awm   = $this->_initAwmQueue();
        $count = 0;
        $time  = microtime(1);
        do {
            $rand = mt_rand(1, 10000);
            $info = $this->_initAwmQueue()->createBackgroundTaskSequence(
                'CheckTime', $params + ['event' => $rand, 'date' => date('Y-m-d H:i:s')], AwmQueue\AwmClient::PRIORITY_HIGH
            );
            var_dump($info);
            $count++;
        } while ($count <= 1);
        echo "\n" . sprintf('%0.10f', microtime(1) - $time) . "\n";
    }
    
    private function _initAwmQueue()
    {
        global $config;
        
        try {
            $awm = AwmQueue\AwmClient::getInstance();
            if ($awm->setConfig($config)->isReadyToUse()) {
                return $awm;
            } else {
                throw new Exception('AwmQueue is not ready');
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }
    
    private function _initAwmQueueSequence()
    {
        $awm = $this->_initAwmQueue();
        
        try {
            if ($awm->isSequenceAvailable()) {
                $config = $awm->getSequenceConfig();
                return AwmQueue\Sequence\FactorySequence::create($config['driver'], $config);
            } else {
                throw new Exception('AwmQueueSequence is not ready');
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }
    
}

(new AwmQueueSequence)->run();
//(new AwmQueueSequence)->loadTasks();
//(new AwmQueueSequence)->truncate();

usleep(500000);
