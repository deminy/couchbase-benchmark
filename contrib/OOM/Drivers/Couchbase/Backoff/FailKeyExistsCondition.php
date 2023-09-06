<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase\Backoff;

use Exception;

/**
 * Class FailKeyExistsCondition
 * Don't retry if a Couchbase KEY EXISTS exception thrown out.
 */
class FailKeyExistsCondition extends BaseRetryCondition
{
    public function met($result, ?\Exception $e): bool
    {
        if (($e instanceof \CouchbaseException) && ($e->getCode() === COUCHBASE_KEY_EEXISTS)) {
            return true;
        }

        return parent::met($result, $e);
    }
}
