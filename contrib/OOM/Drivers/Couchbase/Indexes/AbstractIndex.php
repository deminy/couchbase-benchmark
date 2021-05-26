<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase\Indexes;

use Crowdstar\OOM\Drivers\Couchbase\AbstractDriver;

abstract class AbstractIndex
{
    protected AbstractDriver $driver;

    protected string $indexKey;

    public function __construct(AbstractDriver $driver, string $indexKey)
    {
        $this->driver   = $driver;
        $this->indexKey = $indexKey;
    }

    abstract public function add(string $id): bool;

    abstract public function remove(string $id): bool;
}
