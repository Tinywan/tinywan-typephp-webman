<?php
use support\App;
use Workerman\Worker;

function main(): void
{
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', getcwd());
    }

    $autoload = getcwd() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    ini_set('display_errors', 'on');
    error_reporting(E_ALL);

    // 0. 初始化 Workerman 协议与协程组件
    if (class_exists(\Workerman\Protocols\Http\Session::class)) {
        \Workerman\Protocols\Http\Session::init();
    }
    if (class_exists(\Workerman\Protocols\Http\Session\FileSessionHandler::class)) {
        \Workerman\Protocols\Http\Session\FileSessionHandler::init();
    }
    if (class_exists(\Workerman\Coroutine::class)) {
        \Workerman\Coroutine::init();
    }
    if (class_exists(\Workerman\Coroutine\Fiber::class)) {
        \Workerman\Coroutine\Fiber::init();
    }
    if (class_exists(\Workerman\Coroutine\Context::class)) {
        \Workerman\Coroutine\Context::initDriver();
    }
    if (class_exists(\Workerman\Coroutine\Context\Fiber::class)) {
        \Workerman\Coroutine\Context\Fiber::initContext();
    }

    // 1. 加载配置
    App::loadAllConfig(['route', 'container']);

    $errorReporting = config('app.error_reporting');
    if (isset($errorReporting)) {
        error_reporting($errorReporting);
    }

    // 2. 确保 runtime 目录
    $runtimeLogsPath = runtime_path() . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($runtimeLogsPath)) {
        @mkdir($runtimeLogsPath, 0777, true);
    }
    $runtimeViewsPath = runtime_path() . DIRECTORY_SEPARATOR . 'views';
    if (!is_dir($runtimeViewsPath)) {
        @mkdir($runtimeViewsPath, 0777, true);
    }

    // 3. 配置 Workerman 服务属性
    $serverConfig = config('server', []);
    Worker::$pidFile = $serverConfig['pid_file'] ?? (runtime_path() . '/webman.pid');
    Worker::$stdoutFile = $serverConfig['stdout_file'] ?? (runtime_path() . '/logs/stdout.log');
    Worker::$logFile = $serverConfig['log_file'] ?? (runtime_path() . '/logs/workerman.log');

    // 4. 启动 Webman HTTP 进程
    $process = config('process', []);
    if (isset($process['webman'])) {
        worker_start('webman', $process['webman']);
    } else {
        foreach ($process as $processName => $procConfig) {
            worker_start($processName, $procConfig);
        }
    }

    // 5. 进入事件循环监听
    Worker::runAll();
}
