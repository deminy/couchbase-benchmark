<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase\Indexes;

use Crowdstar\OOM\Drivers\Couchbase\AbstractDriver;
use stdClass;

class Index extends AbstractIndex
{
    protected array $ids;

    public function __construct(AbstractDriver $driver, string $indexKey, array $ids = [], bool $fromDb = true)
    {
        parent::__construct($driver, $indexKey);
        $this->ids = ($fromDb ? $ids : array_fill_keys($ids, null));
    }

    /**
     * {@inheritDoc}
     * @throws CouchbaseException
     * @SuppressWarnings(PHPMD.ShortVariable)
     * @todo batch adding.
     * @todo sort before save for searching with pagination later on???
     */
    public function add(string $id): bool
    {
        if (!empty($id)) {
            if (!array_key_exists($id, $this->ids)) {
                $this->ids[$id] = null;
                $this->driver->upsert($this->indexKey, $this->dbObject());
            }

            return true;
        }

        return false;
    }

    /**
     * @todo batch deletion.
     * {@inheritDoc}
     * @throws CouchbaseException
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function remove(string $id): bool
    {
        if (!empty($id) && array_key_exists($id, $this->ids)) {
            unset($this->ids[$id]);
            $this->driver->upsert($this->indexKey, $this->dbObject());
        }

        return true;
    }

    public function count(): int
    {
        return count($this->ids);
    }

    public function getIds(): array
    {
        return array_keys($this->ids);
    }

    /**
     * @todo remove if not in use.
     */
    public function isEmpty(): bool
    {
        return empty($this->ids);
    }

    protected function dbObject(): stdClass
    {
        return (object) ['ids' => $this->ids];
    }
}
