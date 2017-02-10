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

class FactorySequence
{
    
    /**
     * Create AbstractSequence class instance
     *
     * @param string $class
     * 
     * @throws ExceptionSequence
     * 
     * @return AwmQueue\Sequence\AbstractSequence
     */
    public static function create($class, array $params)
    {
        if (\array_search($class, static::getAllowedDrivers()) === false) {
            throw new ExceptionSequence("Class '{$class}' is not in allowed drivers list");
        }
        
        $className = 'AwmQueue\\Sequence\\Drivers\\' . $class;
        
        return new $className($params);
    }
    
    /**
     * Get allowed drivers
     *
     * @param string $callDriver
     * 
     * @return bool|array
     */
    public static function getAllowedDrivers($callDriver = null)
    {
        static $allowedDrivers = [];
        
        if (empty($allowedDrivers)) {
            foreach (\glob(__DIR__ . DIRECTORY_SEPARATOR . 'Drivers' . DIRECTORY_SEPARATOR . '*.php') as $driver) {
                require_once($driver);
                $driverName = \str_replace('.php', '', \basename($driver));
                $rc = new \ReflectionClass('AwmQueue\\Sequence\\Drivers\\' . $driverName);
                if ($rc->isSubclassOf(new \ReflectionClass('AwmQueue\\Sequence\\AbstractSequence'))) {
                    $allowedDrivers[$driverName] = $driverName;
                }
            }
        }
        
        return $callDriver === null ? $allowedDrivers : (isset($allowedDrivers[$callDriver]) ? $allowedDrivers[$callDriver] : false);
    }
    
}
