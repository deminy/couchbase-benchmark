<?php

declare(strict_types=1);

use App\Service\CouchbaseProxyFactory;
use App\Service\CouchbaseProxyInterface;

return [
    CouchbaseProxyInterface::class => CouchbaseProxyFactory::class,
];
