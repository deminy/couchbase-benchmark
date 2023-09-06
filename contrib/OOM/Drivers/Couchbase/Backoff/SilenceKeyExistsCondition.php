<?php

declare(strict_types=1);

namespace Crowdstar\OOM\Drivers\Couchbase\Backoff;

use Exception;

/**
 * Class SilenceKeyExistsCondition
 */
class SilenceKeyExistsCondition extends BaseRetryCondition
{
    public function met($result, ?\Exception $e): bool
    {
        if (($e instanceof \CouchbaseException) && ($e->getCode() === COUCHBASE_KEY_EEXISTS)) {
            // Don't throw out an exception when the Couchbase key already exists.
            $this->throwable = false;
            return true;
        }

        return parent::met($result, $e);
    }
}
