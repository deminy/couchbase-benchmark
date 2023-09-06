<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase;

use Crowdstar\OOM\Exception;

class StandardDriver extends AbstractDriver
{
    final protected function getEntityKey(string $schema, string $id): string
    {
        if (array_key_exists($id, self::RESERVED_IDS)) {
            throw new Exception("Couchbase entity ID '{$id}' is reserved.");
        }

        return "{$schema}:{$id}";
    }

    final protected function getCounterKey(string $schema): string
    {
        return "{$schema}:counter";
    }

    /**
     * @todo replace MD5
     * @todo use entity object.
     */
    final protected function getIndexKey(string $schema, string $key, string $value): string
    {
        return "idx:{$schema}:{$key}:" . md5($value);
    }

    /**
     * @todo replace MD5
     * @todo use entity object.
     */
    final protected function getDoubleUniqueIndexKey(string $schema, string $key1, string $key2, string $value1): string
    {
        return "idx:{$schema}:{$key1}:{$key2}:" . md5($value1);
    }
}
