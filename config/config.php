<?php

declare(strict_types=1);

use App\LogHelper;
use Hyperf\Contract\StdoutLoggerInterface;

use function Hyperf\Support\env;

return [
    'app_name'                   => env('APP_NAME', 'demo'),
    'app_env'                    => env('APP_ENV', 'local'),
    'scan_cacheable'             => env('SCAN_CACHEABLE', false),
    StdoutLoggerInterface::class => [
        'log_level' => LogHelper::getLevelsAboveOrEqualTo(),
    ],
];
