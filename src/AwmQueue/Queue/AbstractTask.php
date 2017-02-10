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

namespace AwmQueue\Queue;

abstract class AbstractTask
{
    
    /**
     * Default task class method
     *
     * @param \GearmanJob $job
     * @param \AwmQueue\AwmWorker $worker
     * 
     * @return void
     */
    abstract public function execute(\GearmanJob $job, \AwmQueue\AwmWorker $worker);
    
    /**
     * Mark this job as completed. Please notice that we will skip 5 attempts
     *
     * @param \GearmanJob $job
     * @param \AwmQueue\AwmWorker $worker
     * @param bool $skipLog - optional
     * 
     * @return void
     */
    protected function _markJobAsCompleted(\GearmanJob $job, \AwmQueue\AwmWorker $worker, $skipLog = false)
    {
        if (!$skipLog) {
            $worker->setInfoMessage('Job "%s" marked as completed', $job->unique());
        }
        
        $job->sendComplete(GEARMAN_SUCCESS);
    }
    
    /**
     * Mark this job as failed. Please notice that we will skip 5 attempts
     *
     * @param \GearmanJob $job
     * @param \AwmQueue\AwmWorker $worker
     * @param bool $skipLog - optional
     * 
     * @return void
     */
    protected function _markJobAsFailed(\GearmanJob $job, \AwmQueue\AwmWorker $worker, $skipLog = false)
    {
        if (!$skipLog) {
            $worker->setInfoMessage('Job "%s" marked as failed', $job->unique());
        }
        
        $job->setReturn(GEARMAN_WORK_FAIL);
        $job->sendException(GEARMAN_WORK_FAIL);
        $job->sendFail();
    }
    
    /**
     * Try to execute job one more time. Please notice that we have 5 attempts
     *
     * @param \GearmanJob $job
     * @param \AwmQueue\AwmWorker $worker
     * @param int $sleep - optional
     * @param bool $skipLog - optional
     * 
     * @return void
     */
    protected function _tryExecuteJobAgain(\GearmanJob $job, \AwmQueue\AwmWorker $worker, $sleep = 0, $skipLog = false)
    {
        $sleep = (int) $sleep;
        if ($sleep <= 0) {
            $sleep = 0;
        }
        
        if (!$skipLog) {
            $worker->setErrorMessage('Try to execute job "%s" again in "%u" seconds', $job->unique(), $sleep);
        }
        
        if ($sleep) {
            \sleep($sleep);
        }
        
        $job->setReturn(GEARMAN_WORK_FAIL);
        $job->sendException(GEARMAN_WORK_FAIL);
        
        exit(GEARMAN_WORK_FAIL);
    }
    
}
