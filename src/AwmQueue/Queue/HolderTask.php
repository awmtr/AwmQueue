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

final class HolderTask extends \SplObjectStorage
{
    
    /**
     * Attach and get key of the object
     *
     * @param HolderTaskObject $object
     * @param mixed $data - optional
     * 
     * @return string
     */
    public function attachWithKey(HolderTaskObject $object, $data = null)
    {
        parent::attach($object, $data);
        
        return static::getHash($object);
    }
    
    /**
     * Get object by key
     *
     * @param string $key
     * 
     * @return false|HolderTaskObject
     */
    public function retrieveByKey($key)
    {
        $this->rewind();
        while ($this->valid()) {
            $obj = $this->current();
            if ($key == static::getHash($obj)) {
                return $obj;
            }
            $this->next();
        }
        
        return false;
    }
    
    /**
     * Group objects by task name
     *
     * @param string $key
     * 
     * @return array
     */
    public function groupByTaskName()
    {
        $arr = [];
        
        $this->rewind();
        while ($this->valid()) {
            $obj = $this->current();
            $arr[$obj->getTaskName()][$obj->getPriority()][] = $obj->getParams();
            $this->next();
        }
        
        return $arr;
    }
    
}
