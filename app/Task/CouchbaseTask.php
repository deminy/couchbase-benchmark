<?php

declare(strict_types=1);

namespace App\Task;

use App\Service\CouchbaseProxyInterface;
use Couchbase\Document;
use CrowdStar\Backoff\ExponentialBackoff;
use Crowdstar\CouchbaseAdapter\CouchbaseAdapter;
use Crowdstar\OOM\Drivers\Couchbase\Backoff\BaseRetryCondition;
use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\Task\Annotation\Task;

/**
 * Before calling a Couchbase command through exponential backoff, we get a Couchbase connection first.
 *   * That getConnection() method call could fail or throw out an exception. In this case, let it go; this will fail
 *     current request, but next request will try to make a new connection (if needed).
 */
class CouchbaseTask
{
    protected CouchbaseAdapter $connection;

    /**
     * @param array|string $ids
     * @return array|mixed|null
     */
    #[Task(timeout: -1)]
    final public function get($ids, array $options, string $retryConditionClass)
    {
        $connection = $this->getConnection();
        $data       = $this->getBackoff($connection, $retryConditionClass)->run(
            function () use ($connection, $ids, $options) {
                return $connection->get($ids, $options);
            }
        );

        if (is_array($data)) {
            foreach ($data as $key => $doc) {
                $data[$key] = $this->getValue($doc);
            }

            return $data;
        }

        return $this->getValue($data);
    }

    /**
     * @param array|string $ids
     * @return array|Document document or list of the documents
     * @todo which backoff condition?
     */
    #[Task(timeout: -1)]
    final public function upsert($ids, $value, array $options, string $retryConditionClass)
    {
        $connection = $this->getConnection();
        return $this->getBackoff($connection, $retryConditionClass)->run(
            function () use ($connection, $ids, $value, $options) {
                return $connection->upsert($ids, $value, $options);
            }
        );
    }

    /**
     * @param array|string $ids
     * @return array|Document document or list of the documents
     * @todo which backoff condition?
     */
    #[Task(timeout: -1)]
    final public function insert($ids, $value, array $options, string $retryConditionClass)
    {
        $connection = $this->getConnection();
        return $this->getBackoff($connection, $retryConditionClass)->run(
            function () use ($connection, $ids, $value, $options) {
                return $connection->insert($ids, $value, $options);
            }
        );
    }

    /**
     * @param array|string $ids
     * @return array|Document document or list of the documents
     * @todo which backoff condition?
     */
    #[Task(timeout: -1)]
    final public function replace($ids, $value, array $options, string $retryConditionClass)
    {
        $connection = $this->getConnection();
        return $this->getBackoff($connection, $retryConditionClass)->run(
            function () use ($connection, $ids, $value, $options) {
                return $connection->replace($ids, $value, $options);
            }
        );
    }

    /**
     * @todo error handling.
     */
    #[Task(timeout: -1)]
    final public function remove($ids, array $options, string $retryConditionClass): void
    {
        $connection = $this->getConnection();
        $data       = $this->getBackoff($connection, $retryConditionClass)->run(
            function () use ($connection, $ids, $options) {
                return $connection->remove($ids, $options);
            }
        );

        if (!is_array($data) && !empty($data->error)) { // TODO:
            if ($data->error->getCode() !== COUCHBASE_KEY_ENOENT) {
                throw $data->error;
            }
        }
    }

    /**
     * @param array|string $ids
     */
    #[Task(timeout: -1)]
    final public function getAndLock($ids, int $lockTime, array $options, string $retryConditionClass)
    {
        $connection = $this->getConnection();
        return $this->getBackoff($connection, $retryConditionClass)->run(
            function () use ($connection, $ids, $lockTime, $options) {
                return $connection->getAndLock($ids, $lockTime, $options);
            }
        );
    }

    /**
     * @param array|string $ids
     * @return array|int
     * @todo return value
     */
    #[Task(timeout: -1)]
    final public function counter($ids, int $delta, array $options, string $retryConditionClass)
    {
        $options    = $options ?: ['initial' => 1];
        $connection = $this->getConnection();
        $data       = $this->getBackoff($connection, $retryConditionClass)->run(
            function () use ($connection, $ids, $delta, $options) {
                return $connection->counter($ids, $delta, $options);
            }
        );

        if (is_array($data)) {
            foreach ($data as $key => $doc) {
                $data[$key] = $this->getValue($doc);
            }

            return $data;
        }

        return $this->getValue($data);
    }

    #[Task(timeout: -1)]
    final public function info(): array
    {
        $connection = $this->getConnection();
        return $this->getBackoff($connection)->run(
            function () use ($connection): array {
                return $connection->manager()->info();
            }
        );
    }

    #[Task(timeout: -1)]
    final public function flushBucket(): void
    {
        $connection = $this->getConnection();
        $this->getBackoff($connection)->run(
            function () use ($connection) {
                $connection->manager()->flush();
            }
        );
    }

    protected function getConnection(): CouchbaseAdapter
    {
        if (!isset($this->connection)) {
            $this->connection = ApplicationContext::getContainer()->get(CouchbaseProxyInterface::class);
        }

        return $this->connection->getActiveConnection();
    }

    protected function getBackoff(CouchbaseAdapter $connection, string $retryConditionClass = BaseRetryCondition::class, int $maxAttempts = 2): ExponentialBackoff
    {
        $retryCondition = new $retryConditionClass($connection);
        if (!($retryCondition instanceof BaseRetryCondition)) {
            throw new \Exception("Class {$retryConditionClass} must be a child class of " . BaseRetryCondition::class);
        }
        return (new ExponentialBackoff($retryCondition))->setMaxAttempts($maxAttempts);
    }

    /**
     * @return mixed|null
     */
    protected function getValue(?Document $doc)
    {
        if (empty($doc)) {
            return null;
        }
        if (!empty($doc->error)) {
            if ($doc->error->getCode() === COUCHBASE_KEY_ENOENT) {
                return null;
            }
        }

        return $doc->value;
    }
}
