### CLIENT simple usage example

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

### WORKER simple usage example

require_once 'src/AwmQueue/AwmWorker.php';
require_once 'src/config.php';

AwmQueue\AwmWorker::getInstance()->setConfig($config)->run();
