<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase\Backoff;

use CouchbaseException;
use CrowdStar\Backoff\AbstractRetryCondition;
use Crowdstar\CouchbaseAdapter\CouchbaseAdapter;
use Exception;
use Psr\Log\LogLevel;

class BaseRetryCondition extends AbstractRetryCondition
{
    protected bool $throwable = true;

    protected CouchbaseAdapter $adapter;

    public function __construct(CouchbaseAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function throwable(): bool
    {
        return $this->throwable;
    }

    /**
     * For error code LCB_ETMPFAIL and COUCHBASE_ETMPFAIL, please check following documents:
     * @see https://docs.couchbase.com/sdk-api/couchbase-c-client-2.10.6/group__lcb-error-codes.html#ggac1f5be170e51b1bfbe1befb886cc7173aaa2250e38e81a2bd885153f56c0b4c40
     * @see https://docs.huihoo.com/couchbase/developer-guide/c-2.4/handling-errors.html Handling errors
     * @see https://github.com/couchbaselabs/lcbook#error-handling-1 Error Handling
     *
     * Following exceptions should be handled at child classes:
     *   * COUCHBASE_KEY_ENOENT:  Key not found.
     *   * COUCHBASE_KEY_EEXISTS: Key already exists.
     *
     * {@inheritdoc}
     * @throws Exception
     */
    public function met($result, ?Exception $e): bool
    {
        if (!empty($e)) {
            if ($e instanceof CouchbaseException) {
                if ($e->getCode() === COUCHBASE_ETMPFAIL) {
                    // Log it at DEBUG level here since the exception will be thrown out from the call, causing error log written to the default logger.
                    $this->log(LogLevel::DEBUG, "Failed to query on Couchbase due to error code {$e->getCode()} (probably server busy).");
                    return true;
                }

                // Reconnect only when needed.
                //
                // Couchbase errors like COUCHBASE_KEY_ENOENT and COUCHBASE_KEY_EEXISTS shouldn't reach the code here, since
                // they should have been handled by a child class already (before reaching this IF statement).
                $this->log(LogLevel::ERROR, "Reconnecting to Couchbase due to an uncaught exception ({$e->getCode()}): {$e->getMessage()}");
                $this->adapter->reconnect(); // If an exception thrown out here, let it thrown out to fail current request.
                return false;
            }

            // Log it at DEBUG level here since the exception will be thrown out from the call, causing error log written to the default logger.
            $this->log(
                LogLevel::DEBUG,
                'Couchbase operation failed due to: ' . $e->getMessage(),
                [
                    'code'  => $e->getCode(),
                    'class' => get_class($e),
                ]
            );
        }

        return true;
    }

    protected function log(string $level, string $message, array $context = []): self
    {
        $this->adapter->log($level, $message, $context);

        return $this;
    }
}
