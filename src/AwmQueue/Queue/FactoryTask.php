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

class FactoryTask
{
    
    /**
     * Create AbstractTask class instance
     *
     * @param string $class
     * @param string $tasksFolderPath - optional
     * 
     * @throws ExceptionTask
     * 
     * @return AwmQueue\Queue\AbstractTask
     */
    public static function create($class, $tasksFolderPath = null)
    {
        if (\array_search($class, static::getAllowedTasks(null, $tasksFolderPath)) === false) {
            throw new ExceptionTask("Class '{$class}' is not in allowed tasks list");
        }
        
        $className = 'AwmQueue\\Queue\\Tasks\\' . $class;
        
        return new $className;
    }
    
    /**
     * Get allowed methods
     *
     * @param string $callTask - optional
     * @param string $tasksFolderPath - optional
     * 
     * @return bool|array
     */
    public static function getAllowedTasks($callTask = null, $tasksFolderPath = null)
    {
        static $allowedTasks = [];
        
        if (empty($allowedTasks)) {
            if (is_null($tasksFolderPath)) {
                $tasksFolderPath = __DIR__ . DIRECTORY_SEPARATOR . 'Tasks' . DIRECTORY_SEPARATOR;
            } else {
                $tasksFolderPath = rtrim($tasksFolderPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
            foreach (\glob($tasksFolderPath . 'Task*.php') as $task) {
                require_once($task);
                $taskName = \str_replace('.php', '', \basename($task));
                $rc = new \ReflectionClass('AwmQueue\\Queue\\Tasks\\' . $taskName);
                if ($rc->hasMethod('execute') && $rc->getMethod('execute')->isPublic()) {
                    $allowedTasks[\preg_replace('/^Task/', '', $taskName)] = $taskName;
                }
            }
        }
        
        return $callTask === null ? $allowedTasks : (isset($allowedTasks[$callTask]) ? $allowedTasks[$callTask] : false);
    }
    
}
