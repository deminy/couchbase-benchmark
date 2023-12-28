<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase;

use CrowdStar\Backoff\ExponentialBackoff;
use Crowdstar\OOM\AbstractDriver as BaseDriver;
use Crowdstar\OOM\AbstractEntity;
use Crowdstar\OOM\Drivers\Couchbase\Backoff\FailNotFoundCondition;
use Crowdstar\OOM\Drivers\Couchbase\Indexes\AbstractIndex;
use Crowdstar\OOM\Drivers\Couchbase\Indexes\Index;
use Crowdstar\OOM\Drivers\Couchbase\Indexes\Unique;
use Crowdstar\OOM\Drivers\Couchbase\Traits\CouchbaseCallsTrait;
use Crowdstar\OOM\Entities\AutoIncrementInterface;
use Crowdstar\OOM\Exception;
use Psr\Log\LoggerInterface;

abstract class AbstractDriver extends BaseDriver
{
    use CouchbaseCallsTrait;

    protected const DEFAULT_LOCK_TIME = 5;

    protected const MAX_RETRIES = 3;

    /**
     * @see AbstractDriver::getReservedSchemaNames()
     * @see \Crowdstar\OOM\AbstractModel::initSchema()
     */
    protected const RESERVED_SCHEMA_NAMES = [
        'idx' => null,
    ];

    /**
     * @see StandardDriver::getEntityKey()
     * @see VersionBasedDriver::getEntityKey()
     */
    protected const RESERVED_IDS = [
        'counter' => null,
    ];

    /**
     * @return \stdClass[]
     * @todo How to deal with non-existing records?
     */
    public function chunk(string $schema, int $start = self::DEFAULT_START, int $stop = self::DEFAULT_STOP): array
    {
        $keys = [];
        for ($i = $start; $i <= $stop; $i++) {
            $keys[] = $this->getEntityKey($schema, (string) $i);
        }

        $data = [];
        if (!empty($keys)) {
            foreach ($this->get($keys) as $row) {
                if (isset($row)) {
                    $data[$row->id] = $row;
                }
            }
        }

        return $data;
    }

    public function find(string $schema, string $value, string $field, bool $lock = false): ?\stdClass
    {
        if ($field === 'id') {
            $id = $value;
        } else {
            $index = $this->getUniqueIndex($schema, $field, $value);
            $id    = $index->getId();
        }

        return $id ? $this->findByKey($this->getEntityKey($schema, $id), $lock) : null;
    }

    /**
     * @todo move it to class DefaultDriver?
     */
    public function findBy(string $schema, string $key, string $value, int $offset = 0, ?int $limit = self::DEFAULT_LENGTH): array
    {
        // single value lookup
        if ($key === 'id') {
            return [$this->getEntityKey($schema, $value)];
        }

        // index based lookup
        $index = $this->getIndex($schema, $key, $value);
        $ids   = $index->getIds();
        if (!empty($ids)) {
            $ids = array_slice($ids, $offset, $limit);
            if (!empty($ids)) {
                $keys = array_map(fn (string $id) => $this->getEntityKey($schema, $id), $ids);

                return $this->findByGeneral($keys);
            }
        }

        return [];
    }

    public function findByIds(string $schema, string ...$ids): array
    {
        if (!empty($ids)) {
            $keys = array_map(fn (string $id) => $this->getEntityKey($schema, $id), $ids);

            return $this->findByGeneral($keys);
        }

        return [];
    }

    /**
     * @return array
     * @throws \CouchbaseException
     */
    public function findByDoubleUnique(string $schema, $fields, $field1Value, $field2Values)
    {
        $getKeys = function () use ($schema, $fields, $field1Value, $field2Values) {
            $keys  = [];
            $index = $this->getDoubleUniqueIndex($schema, $fields, (object) [$fields[0] => $field1Value]);

            return array_map(
                function ($element) use ($schema) {
                    return $this->getEntityKey($schema, $element);
                },
                $index->idsForKeys($field2Values)
            );
        };

        return $this->findByGeneral($getKeys);
    }

    /**
     * @throws \CouchbaseException
     */
    public function findByDoubleUniqueAll(string $schema, array $fields, $field1Value, int $offset = 0, int $limit = null): array
    {
        $getKeys = function () use ($schema, $fields, $field1Value, $offset, $limit) {
            $keys  = [];
            $index = $this->getDoubleUniqueIndex($schema, $fields, (object) [$fields[0] => $field1Value]);

            return array_map(
                function ($element) use ($schema) {
                    return $this->getEntityKey($schema, $element);
                },
                array_slice($index->idsArray(), $offset, $limit)
            );
        };

        return $this->findByGeneral($getKeys);
    }

    /**
     * @throws \CouchbaseException
     */
    public function getDoubleIndexContents(string $schema, array $fields, $field1Value): object
    {
        return $this->getDoubleUniqueIndex($schema, $fields, (object) [$fields[0] => $field1Value])->getIds();
    }

    /**
     * @param string[] $keys
     * @throws \CouchbaseException
     */
    public function findByGeneral(array $keys): array
    {
        $data = [];
        foreach ($this->get($keys) as $row) {
            if (!empty($row)) {
                $data[$row->id] = $row;
            }
        }

        return $data;
    }

    /**
     * returns raw key/object information
     *
     * @todo share code with other object retrieval, avoided for now to prevent regression
     * @throws \CouchbaseException
     */
    public function findByKeyedRaw(string $schema, string $key, string $value, int $offset = 0, int $limit = null): array
    {
        $getKeys = function () use ($schema, $key, $value, $offset, $limit) {
            $keys = [];
            // index based lookup
            $index = $this->getIndex($schema, $key, $value);

            if (!$index->isEmpty()) {
                $keys = array_map(
                    function ($element) use ($schema) {
                        return $this->getEntityKey($schema, $element);
                    },
                    array_slice($index->getIds(), $offset, $limit)
                );
            }

            return $keys;
        };

        return $this->findByKeyedRawGeneral($getKeys);
    }

    /**
     * @throws \CouchbaseException
     */
    public function findAllByDoubleUniqueKeyedRaw(string $schema, array $fields, $field1Value): array
    {
        $getKeys = function () use ($schema, $fields, $field1Value) {
            $keys  = [];
            $index = $this->getDoubleUniqueIndex($schema, $fields, (object) [$fields[0] => $field1Value]);

            return array_map(
                function ($element) use ($schema) {
                    return $this->getEntityKey($schema, $element);
                },
                $index->idsArray()
            );
        };

        return $this->findByKeyedRawGeneral($getKeys);
    }

    /**
     * @param callable $getKeys
     * @throws \CouchbaseException
     */
    public function findByKeyedRawGeneral($getKeys): array
    {
        $cbe = null;
        $ret = [];

        $tries = 1;
        do {
            $keys = [];
            try {
                $keys = $getKeys();

                // convert keys into objects
                if (!empty($keys)) {
                    foreach ($keys as $key) {
                        $rows = $this->get([$key]);
                        foreach ($rows as $row) {
                            if (!empty($row)) {
                                $ret[$key] = $row;
                            }
                        }
                    }
                }
                break;
            } catch (\CouchbaseException $e) {
                if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                    return [];
                }
                if ($e->getCode() === COUCHBASE_ETIMEDOUT) {
                    usleep(ExponentialBackoff::getTimeoutMicroseconds($tries));
                    $tries++;
                } else {
                    app('log')->error("COUCHBASE EXCEPTION: {$key}:" . $e->getCode() . '//' . $e->getMessage());
                    throw $e;
                }
            }
        } while ($tries <= self::MAX_RETRIES);

        return $ret;
    }

    public function chunkBy(string $schema, string $key, $value, int $size, $callback): void
    {
        $getIds = function () use ($schema, $key, $value) {
            $ids = [];
            $idx = $this->getIndex($schema, $key, $value);
            if (!$idx->isEmpty()) {
                $ids = array_unique($idx->getIds(), SORT_NUMERIC);
            }
            return $ids;
        };

        $this->chunkByGeneral($schema, $size, $callback, $getIds);
    }

    public function chunkByDoubleUnique(string $schema, array $fields, $field1Value, int $size, $callback): void
    {
        $getIds = function () use ($schema, $fields, $field1Value) {
            $idx = $this->getDoubleUniqueIndex($schema, $fields, (object) [$fields[0] => $field1Value]);
            return array_unique($idx->idsArray(), SORT_NUMERIC);
        };

        $this->chunkByGeneral($schema, $size, $callback, $getIds);
    }

    /**
     * @param callable $callback
     * @param callable $getIds
     */
    public function chunkByGeneral(string $schema, int $size, $callback, $getIds): void
    {
        // step one: fetch the index data
        $ids   = [];
        $tries = 1;
        do {
            try {
                $ids = $getIds();
                break;
            } catch (\CouchbaseException $e) {
                if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                    return;
                }
                if ($e->getCode() === COUCHBASE_ETIMEDOUT) {
                    usleep(ExponentialBackoff::getTimeoutMicroseconds($tries));
                    $tries++;
                } else {
                    app('log')->error("COUCHBASE EXCEPTION: {$key}:" . $e->getCode() . '//' . $e->getMessage());
                    throw $e;
                }
            }
        } while ($tries <= self::MAX_RETRIES);

        // return empty array when index has nothing
        $total = count($ids);
        if ($total < 1) {
            return;
        }

        // step 2: sort the index
        sort($ids, SORT_NUMERIC);

        // step 3: fetch batches and call user function
        $start = 0;
        while ($start < $total) {
            $keys = array_map(
                function ($id) use ($schema) {
                    return $this->getEntityKey($schema, $id);
                },
                array_slice($ids, $start, $size)
            );

            // convert keys into objects
            $batch = [];
            if (!empty($keys)) {
                $values = [];
                $tries  = 1;
                do {
                    try {
                        // Data fetched here are not cached because they are not kept in the original states. There
                        // were functions like array_unique(), sort(), etc used on the data fetched.
                        $values = $this->get($keys);
                        break;
                    } catch (\CouchbaseException $e) {
                        if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                            return;
                        }
                        if ($e->getCode() === COUCHBASE_ETIMEDOUT) {
                            usleep(ExponentialBackoff::getTimeoutMicroseconds($tries));
                            $tries++;
                        } else {
                            app('log')->error("COUCHBASE EXCEPTION: {$key}:" . $e->getCode() . '//' . $e->getMessage());
                            throw $e;
                        }
                    }
                } while ($tries <= self::MAX_RETRIES);

                if (count($values) < 1) {
                    throw new \Exception(__METHOD__ . "Could not fetch batch {$schema} // {$key} // {$value} for total keys: " . count($keys));
                }

                foreach ($values as $row) {
                    if (!empty($row)) {
                        $batch[$this->getId($row)] = $row;
                    }
                }
            }

            // call user function with a batch of data
            call_user_func($callback, $batch);
            $start += $size;
        }
    }

    /**
     * For the versioned implementation, all econ data have expire time set; thus we don't have to flush econ data manually.
     *
     * {@inheritdoc}
     */
    public function flush(LoggerInterface $logger, string $schema, string $entityClass, int $counter = null): self
    {
        $stop    = static::DEFAULT_STOP;
        $counter = isset($counter) ? $counter : $this->count($schema);
        for ($i = 1; $i <= $counter; $i += $stop) {
            foreach ($this->chunk($schema, $i, $i + $stop - 1) as $obj) {
                $this->delete($schema, $entityClass, $obj);
            }
        }

        // TODO: For non-auto-increment model, there is no need to remove the counter key here.
        $this->remove($this->getCounterKey($schema));

        $logger->notice("{$counter} records deleted from model {$schema}.");

        return $this;
    }

    /**
     * returns raw index information
     *
     * TODO share code with other index retrieval, avoided for now to prevent regression
     *
     * @return array
     * @throws \CouchbaseException
     */
    public function getRawIndex(string $schema, string $key, $value)
    {
        $indexKey = $this->getIndexKey($schema, $key, $value);
        return $this->getRawIndexGeneral($indexKey);
    }

    /**
     * @param object $data
     * @return array
     * @throws \CouchbaseException
     */
    public function getRawDoubleUniqueIndex(string $schema, array $keys, $data)
    {
        $indexKey = $this->getDoubleUniqueIndexKey($schema, $keys, $data);
        return $this->getRawIndexGeneral($indexKey);
    }

    /**
     * @param string $indexKey
     * @return array
     * @throws \CouchbaseException
     */
    public function getRawIndexGeneral($indexKey)
    {
        $data  = null;
        $tries = 1;

        do {
            try {
                $data = $this->get($indexKey);
                break;
            } catch (\CouchbaseException $e) {
                if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                    break;
                }
                if ($e->getCode() === COUCHBASE_ETIMEDOUT) {
                    usleep(ExponentialBackoff::getTimeoutMicroseconds($tries));
                    $tries++;
                } else {
                    app('log')->error(
                        "COUCHBASE EXCEPTION: {$indexKey}:" . $e->getCode() . '//' . $e->getMessage()
                    );
                    throw $e;
                }
            }
        } while ($tries <= self::MAX_RETRIES);

        return [$indexKey, $data];
    }

    /**
     * This method is public to facilitate testing. Should not normally be called from outside this class.
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     * @todo allow to remove an array of index.
     * @todo don't create an index object if not exists!!!
     */
    final public function removeFromIndex(string $schema, string $field, string $value, string $id): bool
    {
        return $this->getIndex($schema, $field, $value)->remove($id);
    }

    /**
     * This method is public for testing only.
     *
     * @param string[] $keys
     * @param object $data
     * @return bool
     * @throws \CouchbaseException
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    final public function removeFromDoubleUniqueIndex(string $schema, array $keys, $data, $id)
    {
        foreach ([$schema, $keys, $data, $id] as $required) {
            if (!$required) {
                return false;
            }
        }
        foreach ($keys as $key) {
            if (empty($data->{$key})) {
                return false;
            }
        }

        $index = $this->getDoubleUniqueIndex($schema, $keys, $data);
        return $index->removeUnique($id);
    }

    /**
     * @param array $keys
     * @param array $field2ValuesToIds
     * @throws \CouchbaseException
     */
    final public function addIdsToDoubleUniqueIndex(string $schema, $keys, $field1Value, $field2ValuesToIds)
    {
        $index = $this->getDoubleUniqueIndex($schema, $keys, (object) [$keys[0] => $field1Value]);
        $index->addIds($field2ValuesToIds);
    }

    final public function create(string $schema, AbstractEntity $entity): AbstractEntity
    {
        if ($entity instanceof AutoIncrementInterface) {
            if (!empty($entity->id)) {
                throw new Exception("The 'id' field is not empty when creating a new entity of " . get_class($entity));
            }
            $this->setEntityId($schema, $entity);
        } else {
            if (empty($entity->id)) {
                throw new Exception("The 'id' field is empty when creating a new entity of " . get_class($entity));
            }
        }

        $this->insert($this->getEntityKey($schema, $entity->id), $entity);
        $this->addIndexes($entity, $schema);
        $this->addUniqueIndexes($entity, $schema);
        // TODO: handle object fields.

        return $entity;
    }

    /**
     * @todo remove parameter $forceReindex if not needed.
     */
    final public function update(string $schema, AbstractEntity $entity, bool $forceReindex = false): bool
    {
        if (empty($entity->id)) {
            // TODO: error log.
            return false;
        }

        $key     = $this->getEntityKey($schema, $entity->id);
        $oldData = $this->get($key);

        if (empty($oldData)) {
            // TODO: error log.
            return false;
        }

        $this->upsert($key, $entity);
        $this->updateIndexes($schema, $entity, $entity::loadData($oldData));

        return true;
    }

    public function delete(string $schema, string $entityClass, \stdClass $data): bool
    {
        if (empty($data) || empty($data->id)) {
            return false;
        }

        $id = $data->id;

        $this->removeUniqueIndexesFromObject($data, $schema, $entityClass); // TODO: batch removing.

        /** @var AbstractEntity $entityClass */
        foreach ($entityClass::INDEXED_FIELDS as $field) {
            if (!empty($data->{$field})) {
                $this->removeFromIndex($schema, $field, $data->{$field}, $data->id); // TODO: batch removing.
            }
        }

        // TODO: remove indexes.
        /*
        foreach ($indexFields['doubleUnique'] as $doubleUniqueIndexedFields) {
            $this->removeFromDoubleUniqueIndex($schema, $doubleUniqueIndexedFields, $data, $id);
        }
        foreach ($indexFields['removed'] as $removedIndexedField) {
            $value = (property_exists($data, $removedIndexedField) ? $data->{$removedIndexedField} : null);
            $this->removeFromIndex($schema, $removedIndexedField, $value, $id);
        }
        */

        $this->remove($this->getEntityKey($schema, $id));

        return true;
    }

    public function count(string $schema): int
    {
        return $this->get($this->getCounterKey($schema)) ?: 0;
    }

    public function countByIndex(string $schema, string $field, string $value): int
    {
        if ($field === 'id') {
            return $this->get($this->getEntityKey($schema, $value)) ? 1 : 0;
        }

        return $this->getIndex($schema, $field, $value)->count();
    }

    /**
     * @param array $fields
     * @todo Not yet implemented.
     */
    public function countByDoubleUnique(string $schema, $fields, $field1Value): int
    {
        $index = $this->getDoubleUniqueIndex($schema, $fields, (object) [$fields[0] => $field1Value]);
        return count($index->idsArray());
    }

    /**
     * To get next numeric ID to be used for new entity to be inserted.
     *
     * @see AbstractDriver::create()
     */
    public function setEntityId(string $schema, AbstractEntity $entity): AbstractEntity
    {
        if (!empty($entity->id)) {
            throw new Exception('ID of the entity has already been set.');
        }

        $entity->id = (string) $this->counter($this->getCounterKey($schema), 1, ['initial' => 1]);

        return $entity;
    }

    final public static function getReservedSchemaNames(): array
    {
        return self::RESERVED_SCHEMA_NAMES;
    }

    abstract protected function getEntityKey(string $schema, string $id): string;

    abstract protected function getCounterKey(string $schema): string;

    abstract protected function getIndexKey(string $schema, string $key, string $value): string;

    abstract protected function getDoubleUniqueIndexKey(string $schema, string $key1, string $key2, string $value1): string;

    /**
     * This should only be called by method $this->find().
     *
     * @see AbstractDriver::find()
     * @todo move to last.
     */
    final protected function findByKey(string $key, bool $lock = false): ?\stdClass
    {
        return $lock ? $this->getAndLock($key, self::DEFAULT_LOCK_TIME) : $this->get($key);
    }

    /**
     * @throws \CouchbaseException
     * @todo move to class StandardDriver??
     * @todo merge with method getUniqueIndex()
     */
    protected function getIndex(string $schema, string $key, string $value): Index
    {
        $indexKey = $this->getIndexKey($schema, $key, $value);
        try {
            $data = $this->get($indexKey, [], FailNotFoundCondition::class);
        } catch (\CouchbaseException $e) {
            // TODO: how about other exceptions???
            if ($e->getCode() == COUCHBASE_KEY_ENOENT) {
                return new Index($this, $indexKey);
            }
        }

        return new Index($this, $indexKey, $data ? (array) $data->ids : []);
    }

    /**
     * @throws \CouchbaseException
     * @todo move to class StandardDriver??
     * @todo merge with method getIndex()
     */
    protected function getUniqueIndex(string $schema, string $key, string $value): Unique
    {
        $indexKey = $this->getIndexKey($schema, $key, $value);
        try {
            $data = $this->get($indexKey, [], FailNotFoundCondition::class);
        } catch (\CouchbaseException $e) {
            // TODO: how about other exceptions???
            if ($e->getCode() == COUCHBASE_KEY_ENOENT) {
                return new Unique($this, $indexKey);
            }
        }

        return new Unique($this, $indexKey, $data ?? null);
    }

    /**
     * @param object $data
     * @throws \CouchbaseException
     */
    protected function getDoubleUniqueIndex(string $schema, array $keys, $data): AbstractDoubleUniqueIndex
    {
        $indexKey  = $this->getDoubleUniqueIndexKey($schema, $keys, $data);
        $indexArgs = ['schema' => $schema, 'keys' => $keys, 'data' => $data, 'driver' => $this];
        return $this->getIndexGeneral($indexKey, $indexArgs, NewDoubleUniqueIndex::class, AbstractDoubleUniqueIndex::class);
    }

    /**
     * @throws \CouchbaseException
     * @todo rename it.
     */
    protected function getIndexGeneral(string $indexKey): AbstractIndex
    {
        try {
            $data = $this->get($indexKey, [], FailNotFoundCondition::class);
        } catch (\CouchbaseException $e) {
            // TODO: how about other exceptions???
            if ($e->getCode() == COUCHBASE_KEY_ENOENT) {
                return new Index($this, $indexKey);
            }
        }

        return new Index($this, $indexKey, $data ? (array) $data->ids : []);
    }

    /**
     * @param string[] $keys
     * @throws \CouchbaseException
     */
    final protected function addToDoubleUniqueIndex(string $schema, array $keys, $data, $id): bool
    {
        foreach ([$schema, $keys, $data, $id] as $required) {
            if (empty($required)) {
                return false;
            }
        }
        foreach ($keys as $key) {
            if (empty($data->{$key})) {
                return false;
            }
        }

        $index = $this->getDoubleUniqueIndex($schema, $keys, $data);
        $index->addUnique($id);
        return true;
    }

    private function addIndexes(AbstractEntity $entity, string $schema, string $field = null): self
    {
        $fields = $field ? [$field] : $entity::INDEXED_FIELDS;
        foreach ($fields as $field) {
            $this->getIndex($schema, $field, $entity->{$field})->add($entity->id);
        }

        return $this;
    }

    private function addUniqueIndexes(AbstractEntity $entity, string $schema, string $field = null): self
    {
        $fields = $field ? [$field] : $entity::UNIQUE_FIELDS;
        foreach ($fields as $field) {
            $this->getUniqueIndex($schema, $field, $entity->{$field})->add($entity->id);
        }

        return $this;
    }

    /**
     * @todo allow to remove an array of index.
     */
    private function removeUniqueIndex(string $schema, string $field, string $value, string $id): self
    {
        $this->getUniqueIndex($schema, $field, $value)->remove($id);

        return $this;
    }

    /**
     * @param AbstractEntity|\stdClass $obj
     * @return $this
     * @todo allow to remove an array of index.
     */
    private function removeUniqueIndexesFromObject($obj, string $schema, string $entityClass): self
    {
        foreach ($entityClass::UNIQUE_FIELDS as $field) {
            $this->removeUniqueIndex($schema, $field, $obj->{$field}, $obj->id);
        }

        return $this;
    }

    /**
     * @param string $schema
     * @param string $uniqueIndexedField
     * @param object $data
     * @param array $alteredFields
     * @return array
     */
    private function maybeAddToUniqueIndex($schema, $uniqueIndexedField, $data, $alteredFields)
    {
        try {
            $this->addUniqueIndexes(
                $schema,
                $uniqueIndexedField,
                $data->{$uniqueIndexedField},
                $this->getId($data)
            );
        } catch (UserMappingsAdIdNotUniqueException $e) {
            Logger::getLogger()->error(
                'UserMappings adId violates uniqueness constraint. Will set adId to null. Exception message: '
                . $e->getMessage()
            );
            $alteredFields['duplicateAdId'] = $data->adId;
            $alteredFields['adId']          = null;
            $data->duplicateAdId            = $data->adId;
            $data->adId                     = null;
            $key                            = $this->getEntityKey($schema, $this->getId($data));
            $this->replace($key, $data);
        }

        return $alteredFields;
    }

    private function updateIndexes(string $schema, AbstractEntity $newEntity, AbstractEntity $oldEntity): bool
    {
        foreach ($newEntity::INDEXED_FIELDS as $field) {
            if (empty($oldEntity->{$field})) {
                $this->addIndexes($newEntity, $schema, $field);
            } elseif ($newEntity->{$field} !== $oldEntity->{$field}) {
                $this->removeFromIndex($schema, $field, $oldEntity->{$field}, $oldEntity->id);
                $this->addIndexes($newEntity, $schema, $field);
            }
        }
        foreach ($newEntity::UNIQUE_FIELDS as $field) {
            if (empty($oldEntity->{$field})) {
                $this->addUniqueIndexes($newEntity, $schema, $field);
            } elseif ($newEntity->{$field} !== $oldEntity->{$field}) {
                $this->removeUniqueIndex($schema, $field, $oldEntity->{$field}, $oldEntity->id);
                $this->addUniqueIndexes($newEntity, $schema, $field);
            }
        }
        // TODO: update double unique indexes.

        return true;
    }
}
