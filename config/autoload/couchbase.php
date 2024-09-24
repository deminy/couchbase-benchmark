<?php

declare(strict_types=1);

return [
    'default' => [
        'protocol'        => env('COUCHBASE_PROTOCOL', 'couchbase'), // Must be "couchbase" or "couchbases".
        'host'            => env('COUCHBASE_HOST', 'couchbase'),
        'user'            => env('COUCHBASE_USER', 'username'),
        'pass'            => env('COUCHBASE_PASS', 'password'),
        'bucket'          => env('COUCHBASE_BUCKET', 'test'),
        'options'         => env('COUCHBASE_OPTIONS', 'wait_for_config=true'),
        'settings'        => [
            # @see docker/rootfilesystem/usr/local/etc/php/conf.d/docker-php-ext-couchbase.ini
            # @see https://github.com/couchbase/php-couchbase/blob/v2.6.2/api/couchbase.php#L47
            'max_idle_time' => (int) env('COUCHBASE_MAX_IDLE_TIME', 40),
        ],
        'verbose_logging' => env('VERBOSE_LOGGING', false),
    ],
];
