<?php

declare(strict_types=1);

namespace Crowdstar\OOM;

use Psr\Log\LoggerInterface;
use stdClass;

abstract class AbstractDriver
{
    public const DEFAULT_START = 1;

    public const DEFAULT_STOP  = 2000;

    /**
     * @see AbstractDriver::findBy()
     * @see AbstractModel::findBy()
     * @see array_slice()
     */
    public const DEFAULT_LENGTH = null;

    abstract public function create(string $schema, AbstractEntity $entity): AbstractEntity;

    abstract public function update(string $schema, AbstractEntity $entity, bool $forceReindex = false): bool;

    abstract public function delete(string $schema, string $entityClass, stdClass $data): bool;

    abstract public function count(string $schema): int;

    /**
     * Similar to $this->findBy(), returns # of results for a given query.
     */
    abstract public function countByIndex(string $schema, string $field, string $value): int;

    /**
     * This method is designed to be called from a predefined method in a model class. You should not directly call
     * this method without using a meaningful value for parameter $start and $stop. For example, you should not make
     * a single method call to this method to fetch all the records of a schema which contains millions of records.
     */
    abstract public function chunk(string $schema, int $start = self::DEFAULT_START, int $stop = self::DEFAULT_STOP): array;

    abstract public function find(string $schema, string $value, string $field, bool $lock = false): ?stdClass;

    /**
     * @param $offset int Skip this many results before returning
     * @param $limit int Max number of results to return
     * @param mixed $value
     */
    abstract public function findBy(string $schema, string $key, string $value, int $offset = 0, int $limit = self::DEFAULT_LENGTH): array;

    abstract public function findByIds(string $schema, string ...$ids): array;

    /**
     * In this method, index data could be cached (for ECON) but item data won't be cached since
     *     1. the callback function may make changes on item data stored.
     *     2. this method doesn't return any data fetched.
     * It's the caller's responsibility to cache the final results if needed.
     * @param mixed $value
     */
    abstract public function chunkBy(string $schema, string $key, $value, int $size, callable $callback): void;

    abstract public function flush(LoggerInterface $logger, string $schema, string $entityClass, ?int $counter = null): self;

    /**
     * @return array an array of reserved schema names, where array keys are schema names
     */
    abstract public static function getReservedSchemaNames(): array;
}
