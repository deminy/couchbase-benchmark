<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase\Traits;

use App\Task\CouchbaseTask;
use Couchbase\Document;
use Couchbase\Exception;
use Crowdstar\OOM\Drivers\Couchbase\Backoff\FailBadValueCondition;
use Crowdstar\OOM\Drivers\Couchbase\Backoff\FailKeyExistsCondition;
use Crowdstar\OOM\Drivers\Couchbase\Backoff\FailNotFoundCondition;
use Crowdstar\OOM\Drivers\Couchbase\Backoff\SilenceNotFoundCondition;
use Crowdstar\OOM\Drivers\Couchbase\VersionBasedDriver;
use Hyperf\Utils\ApplicationContext;

trait CouchbaseCallsTrait
{
    /**
     * @param array|string $ids
     * @throws Exception
     * @return array|mixed|null
     */
    public function get($ids, array $options = [], string $retryConditionClass = SilenceNotFoundCondition::class)
    {
        return $this->getCouchbaseTask()->get($ids, $options, $retryConditionClass);
    }

    /**
     * @param array|string $ids
     * @param mixed $value
     * @return array|Document document or list of the documents
     * @todo which backoff condition?
     */
    public function upsert($ids, $value, array $options = [], string $retryConditionClass = SilenceNotFoundCondition::class)
    {
        return $this->getCouchbaseTask()->upsert($ids, $value, $options, $retryConditionClass);
    }

    /**
     * @param array|string $ids
     * @param mixed $value
     * @return array|Document document or list of the documents
     * @todo which backoff condition?
     */
    final public function insert($ids, $value, array $options = [], string $retryConditionClass = FailKeyExistsCondition::class)
    {
        return $this->getCouchbaseTask()->insert($ids, $value, $this->getOptions($options), $retryConditionClass);
    }

    /**
     * @param array|string $ids
     * @param mixed $value
     * @return array|Document document or list of the documents
     * @todo which backoff condition?
     */
    final public function replace($ids, $value, array $options = [], string $retryConditionClass = FailNotFoundCondition::class)
    {
        return $this->getCouchbaseTask()->replace($ids, $value, $options, $retryConditionClass);
    }

    /**
     * @todo error handling.
     * @param mixed $ids
     */
    final public function remove($ids, array $options = [], string $retryConditionClass = SilenceNotFoundCondition::class): void
    {
        $this->getCouchbaseTask()->remove($ids, $options, $retryConditionClass);
    }

    final public function info(): array
    {
        return $this->getCouchbaseTask()->info();
    }

    final public function flushBucket(): void
    {
        $this->getCouchbaseTask()->flushBucket();
    }

    /**
     * @param array|string $ids
     * @return mixed
     */
    protected function getAndLock($ids, int $lockTime, array $options = [], string $retryConditionClass = SilenceNotFoundCondition::class)
    {
        return $this->getCouchbaseTask()->getAndLock($ids, $lockTime, $options, $retryConditionClass);
    }

    /**
     * @param array|string $ids
     * @return array|int
     */
    final protected function counter($ids, int $delta = 1, array $options = [], string $retryConditionClass = FailBadValueCondition::class)
    {
        return $this->getCouchbaseTask()->counter($ids, $delta, $this->getOptions($options), $retryConditionClass);
    }

    final protected function getCouchbaseTask(): CouchbaseTask
    {
        return ApplicationContext::getContainer()->get(CouchbaseTask::class);
    }

    final protected function getOptions(array $options): array
    {
        if ($this instanceof VersionBasedDriver) {
            $options += VersionBasedDriver::DEFAULT_OPTIONS;
        }

        return $options;
    }
}
