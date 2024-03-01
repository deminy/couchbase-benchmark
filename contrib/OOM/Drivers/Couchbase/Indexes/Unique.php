<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase\Indexes;

use Crowdstar\OOM\Drivers\Couchbase\AbstractDriver;
use Crowdstar\OOM\Exception;

class Unique extends AbstractIndex
{
    protected string $id;

    public function __construct(AbstractDriver $driver, string $indexKey, ?string $id = null)
    {
        parent::__construct($driver, $indexKey);
        if (isset($id)) {
            $this->id = $id;
        }
    }

    public function add(string $id): bool
    {
        if (!empty($this->id)) {
            if ($this->id != $id) {
                throw new Exception("Current unique ID ({$this->id}) doesn't match with given unique ID ({$id}).");
            }
            return true;
        }

        $this->id = $id;
        $this->driver->insert($this->indexKey, $id);

        return true;
    }

    /**
     * @throws CouchbaseException
     * @todo
     * @todo allow to remove multiple keys in one call.
     */
    public function remove(string $id): bool
    {
        $this->driver->remove($this->indexKey);
        unset($this->id);

        return true;
    }

    public function getId(): ?string
    {
        return $this->id ?? null;
    }
}
