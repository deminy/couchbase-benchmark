<?php

declare(strict_types=1);

use App\LogHelper;
use Hyperf\Server\Event;
use Hyperf\Server\Server;
use Swoole\Constant;

return [
    'mode'    => SWOOLE_PROCESS,
    'servers' => [
        [
            'name'      => 'http',
            'type'      => Server::SERVER_HTTP,
            'host'      => '0.0.0.0',
            'port'      => 80,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
            ],
        ],
    ],
    'settings' => [
        Constant::OPTION_ENABLE_COROUTINE    => true,
        Constant::OPTION_WORKER_NUM          => swoole_cpu_num(),
        Constant::OPTION_PID_FILE            => BASE_PATH . '/runtime/hyperf.pid',
        Constant::OPTION_OPEN_TCP_NODELAY    => true,
        Constant::OPTION_MAX_COROUTINE       => 100000,
        Constant::OPTION_OPEN_HTTP2_PROTOCOL => false,
        Constant::OPTION_MAX_REQUEST         => 100000,
        Constant::OPTION_SOCKET_BUFFER_SIZE  => 2 * 1024 * 1024,
        Constant::OPTION_BUFFER_OUTPUT_SIZE  => 2 * 1024 * 1024,
        Constant::OPTION_LOG_LEVEL           => LogHelper::getSwooleLogLevel(),

        Constant::OPTION_TASK_WORKER_NUM       => max(1, swoole_cpu_num() * intval($_SERVER['COUCHBASE_CONN_MULTIPLIER'] ?? 1) - 1),
        Constant::OPTION_TASK_ENABLE_COROUTINE => false,
    ],
    'callbacks' => [
        Event::ON_WORKER_START => [Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
        Event::ON_PIPE_MESSAGE => [Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
        Event::ON_WORKER_EXIT  => [Hyperf\Framework\Bootstrap\WorkerExitCallback::class, 'onWorkerExit'],

        Event::ON_TASK   => [Hyperf\Framework\Bootstrap\TaskCallback::class, 'onTask'],
        Event::ON_FINISH => [Hyperf\Framework\Bootstrap\FinishCallback::class, 'onFinish'],
    ],
];
