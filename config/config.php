<?php

declare(strict_types=1);

use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Log\LogLevel;

use function Hyperf\Support\env;

return [
    'app_name'                   => env('APP_NAME', 'demo'),
    'app_env'                    => env('APP_ENV', 'local'),
    'scan_cacheable'             => env('SCAN_CACHEABLE', false),
    StdoutLoggerInterface::class => [
        'log_level' => [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            // LogLevel::DEBUG,
        ],
    ],
];
