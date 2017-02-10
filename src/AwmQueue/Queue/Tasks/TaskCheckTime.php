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

namespace AwmQueue\Queue\Tasks;

class TaskCheckTime extends \AwmQueue\Queue\AbstractTask
{
    
    /**
     * Check time
     *
     * @param \GearmanJob $job
     * @param \AwmQueue\AwmWorker $worker
     * 
     * @return void
     */
    public function execute(\GearmanJob $job, \AwmQueue\AwmWorker $worker)
    {
        $worker->setInfoMessage('Start job "%s" for "%s"', $job->unique(), __METHOD__);
        
        $params = unserialize($job->workload());
        
        $config = $params['config'];
        $params = $params['params'];
        
        $res = time() < $params['a'];
        
        //throw new \AwmQueue\Queue\ExceptionTask('error');
        
        if ($res) {
            $this->_markJobAsCompleted($job, $worker);    
        }  else {
            $this->_tryExecuteJobAgain($job, $worker, 5);
        }
    }
   
}
