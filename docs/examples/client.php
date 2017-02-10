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

require_once 'src/AwmQueue/AwmClient.php';
require_once 'src/config.php';

if (AwmQueue\AwmClient::getInstance()->setConfig($config)->isReadyToUse()) {
    $res = AwmQueue\AwmClient::getInstance()->createBackgroundTaskSequence('CheckTime', ['a' => 50]);
    print_r($res);
    die;
}
  
if (AwmQueue\AwmClient::getInstance()->setConfig($config)->isReadyToUse()) {
    $res = AwmQueue\AwmClient::getInstance()->createBackgroundTaskHolder('CheckTime', ['a' => 50]);
    print_r($res);
    $res = AwmQueue\AwmClient::getInstance()->createBackgroundTaskHolder('CheckTime', ['a' => 51]);
    print_r($res);
    $res = AwmQueue\AwmClient::getInstance()->createBackgroundTaskHolder('CheckTime', ['a' => 52]);
    print_r($res);
    $res = AwmQueue\AwmClient::getInstance()->sendHoldBackgroundTasks();
    print_r($res);
    die;
}

if (AwmQueue\AwmClient::getInstance()->setConfig($config)->isReadyToUse()) {
    $res = AwmQueue\AwmClient::getInstance()->createBackgroundTask('CheckTime', ['a' => 50]);
    print_r($res);
    die;
}

if (AwmQueue\AwmClient::getInstance()->setConfig($config)->isReadyToUse()) {
    $res = AwmQueue\AwmClient::getInstance()->createTask('CheckTime', ['a' => 50]);
    print_r($res);
    die;
}

echo '---done---';
