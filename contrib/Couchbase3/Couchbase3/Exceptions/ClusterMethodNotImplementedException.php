<?php

declare(strict_types=1);

namespace Crowdstar\Couchbase3\Exceptions;

use Crowdstar\Couchbase3\Cluster;

class ClusterMethodNotImplementedException extends AbstractMethodNotImplementedException
{
    protected string $class = Cluster::class;
}
