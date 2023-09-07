<?php

declare(strict_types=1);

namespace Crowdstar\Couchbase3\Exceptions;

use Crowdstar\Couchbase3\Bucket;

class BucketMethodNotImplementedException extends Exception
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('Method \%s::%s() has not yet been implemented.', Bucket::class, $name));
    }
}
