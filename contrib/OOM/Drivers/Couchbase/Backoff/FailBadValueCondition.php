<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase\Backoff;

use CouchbaseException;
use Exception;

/**
 * Class FailBadValueCondition
 * Don't retry if a Couchbase LCB_DELTA_BADVAL exception thrown out.
 */
class FailBadValueCondition extends BaseRetryCondition
{
    /**
     * {@inheritdoc}
     */
    public function met($result, ?Exception $e): bool
    {
        if (($e instanceof CouchbaseException) && ($e->getCode() === COUCHBASE_DELTA_BADVAL)) {
            return true;
        }

        return parent::met($result, $e);
    }
}
