<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase\Backoff;

use CouchbaseException;
use Exception;

/**
 * Class FailNotFoundCondition
 * Don't retry if a Couchbase NOT FOUND exception thrown out.
 */
class FailNotFoundCondition extends BaseRetryCondition
{
    /**
     * {@inheritdoc}
     */
    public function met($result, ?Exception $e): bool
    {
        if (($e instanceof CouchbaseException) && ($e->getCode() === COUCHBASE_KEY_ENOENT)) {
            return true;
        }

        return parent::met($result, $e);
    }
}
