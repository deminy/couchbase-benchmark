<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase\Backoff;

use CouchbaseException;
use Exception;

/**
 * Class SilenceNotFoundCondition
 * Do a retry if Couchbase exceptions happen (except the NOT FOUND one) when querying against Couchbase.
 */
class SilenceNotFoundCondition extends BaseRetryCondition
{
    /**
     * {@inheritdoc}
     */
    public function met($result, ?Exception $e): bool
    {
        if (($e instanceof CouchbaseException) && ($e->getCode() === COUCHBASE_KEY_ENOENT)) {
            // Don't throw out an exception when the Couchbase item(s) not found.
            $this->throwable = false;
            return true;
        }

        return parent::met($result, $e);
    }
}
