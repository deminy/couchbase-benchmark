<?php

declare(strict_types=1);

namespace Crowdstar\Couchbase3;

use Couchbase\Cluster as Cluster3;
use Couchbase\ClusterOptions as ClusterOptions3;
use Crowdstar\Couchbase3\Exceptions\ClusterMethodNotImplementedException;

class Cluster
{
    /**
     * A newly-added property for the Couchbase 2 driver.
     */
    private string $connectionString;

    /**
     * A newly-added property for the Couchbase 2 driver.
     */
    private Cluster3 $cluster3;

    /**
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function __construct(string $connstr)
    {
        $this->connectionString = $connstr;
    }

    /**
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function openBucket(string $name = 'default', string $password = '')
    {
        return new Bucket($this, $name);
    }

    /**
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function manager(string $username = null, string $password = null)
    {
        throw new ClusterMethodNotImplementedException();
    }

    /**
     * @param \Couchbase\Authenticator $authenticator
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function authenticate($authenticator)
    {
        throw new ClusterMethodNotImplementedException();
    }

    /**
     * @Couchbase2 A built-in Couchbase 2 method.
     */
    public function authenticateAs(string $username, string $password)
    {
        $options = new ClusterOptions3();
        $options->credentials($username, $password);
        $this->cluster3 = new Cluster3($this->connectionString, $options);
    }

    /**
     * A newly-added method for the Couchbase 2 driver.
     */
    public function getCluster3(): Cluster3
    {
        return $this->cluster3;
    }
}
