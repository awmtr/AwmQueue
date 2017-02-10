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

ini_set('error_reporting', -1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$config = [
    'servers'               => [
        ['host' => '127.0.0.1', 'port' => 4730]
    ],
    'environment'           => 'local',           // 'local' || 'dev' || 'prod'
    'ready_to_use'          => true,              // true or false
    'client_timeout'        => 5000,
    'logger_callback'       => 'error_log',
    'logger_level'          => 7,                 // 1 - only errors, 2 - only info, 4 - only debug, 7 - all
    'notify_error_callback' => 'error_log',
    'debug'                 => true,              // true or false
    'tasks_folder_path'     => null,
    'excluded_tasks'        => ['TaskCheckTime'], // Skip this tasks for all registered workers. Instead of this using for single task mode
    'sequence'              => [
        'driver'   => 'PDOMysql',
        'host'     => 'localhost',
        'user'     => 'root',
        'password' => 'root',
        'database' => 'test',
        'port'     => null,
        'table'    => 'awm_queue_sequence'
    ]
];
