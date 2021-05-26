<?php

declare(strict_types=1);

return [
    'default' => [
        'host'           => env('REDIS_HOST', 'redis'),
        'auth'           => env('REDIS_AUTH', null),
        'port'           => (int) env('REDIS_PORT', 6379),
        'db'             => (int) env('REDIS_DB', 0),
        'timeout'        => 0.0,
        'reserved'       => null,
        'retry_interval' => 0,
        'cluster'        => [
            'enable' => !empty(env('REDIS_CLUSTER_SEEDS')),
            'name'   => null,
            'seeds'  => explode(',', env('REDIS_CLUSTER_SEEDS', 'redis:7000,redis:7001,redis:7002')),
        ],
        'sentinel' => [
            'enable'       => (bool) env('REDIS_SENTINEL_ENABLE', false),
            'master_name'  => env('REDIS_MASTER_NAME', 'mymaster'),
            'nodes'        => explode(';', env('REDIS_SENTINEL_NODE', '')),
            'persistent'   => '',
            'read_timeout' => 0,
        ],
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout'    => 3.0,
            'heartbeat'       => -1,
            'max_idle_time'   => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
    ],
];
