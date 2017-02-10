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

class HolderTaskObject
{
    
    private $_taskName = null,
            $_params   = null,
            $_priority = null;
    
    public function __construct($taskName, array $params, $priority)
    {
        $this->_taskName = $taskName;
        $this->_params   = $params;
        $this->_priority = $priority;
    }
    
    public function getTaskName()
    {
        return $this->_taskName;
    }
    
    public function getParams()
    {
        return $this->_params;
    }
    
    public function getPriority()
    {
        return $this->_priority;
    }
    
}
