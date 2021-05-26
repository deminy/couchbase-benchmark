<?php

declare(strict_types=1);

namespace Crowdstar\CouchbaseAdapter;

use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\Exception;
use Psr\Log\LoggerInterface;

/**
 * @mixin \Couchbase\Bucket
 */
class CouchbaseAdapter
{
    /**
     * The config array should be like this:
     *   [
     *     'host'   => 'couchbase',
     *     'user'   => 'username',
     *     'pass'   => 'password',
     *     'bucket' => 'test',
     *   ]
     *
     * @var string[]
     */
    protected array $config;

    protected Bucket $bucket;

    protected float $lastUseTime = 0.0;

    protected LoggerInterface $logger;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Execute a Couchbase command after getting an active connection with method $this->getActiveConnection().
     *
     * @see \Crowdstar\CouchbaseAdapter\CouchbaseAdapter::getActiveConnection()
     */
    public function __call(string $name, array $arguments)
    {
        return $this->bucket->{$name}(...$arguments);
    }

    /**
     * @throws Exception
     */
    public function getActiveConnection(): self
    {
        if (!$this->check()) {
            $this->reconnect();
        }

        return $this;
    }

    /**
     * @see https://github.com/couchbase/php-couchbase/blob/v2.6.3/api/couchbase.php#L47  couchbase.pool.max_idle_time_sec
     * @see https://github.com/couchbase/php-couchbase/blob/v2.6.3/api/couchbase.php#L562 \Couchbase\Bucket::$htconfigIdleTimeout
     * @throws Exception
     */
    public function reconnect(): bool
    {
        $this->logger->debug('Connecting to Couchbase.');

        $this->close();

        $connectionString = "couchbase://{$this->config['host']}";
        if (!empty($this->config['options'])) {
            $connectionString .= '?' . http_build_query($this->config['options']);
        }

        // TODO: handle connection failures.
        $cluster = new Cluster($connectionString);
        $cluster->authenticateAs($this->config['user'], $this->config['pass']);
        $this->bucket = $cluster->openBucket($this->config['bucket']);

        $this->lastUseTime = microtime(true);

        return true;
    }

    /**
     * There is no documentation talking about closing Couchbase connections in detail.
     *
     * @see https://github.com/couchbase/php-couchbase/blob/v2.6.3/api/couchbase.php
     */
    public function close(): void
    {
        // If a new connection is not established successfully after calling this method (like what we did in method
        // $this->reconnect()) in current HTTP request, the next HTTP request will force to reconnect to Couchbase
        // through method $this->getActiveConnection(), since we already have property $this->lastUseTime reset here.
        //
        // To explain it more clearly (assuming only one task worker there in the server):
        //   1. In the 1st HTTP request:
        //      \App\Task\CouchbaseTask::get()
        //         $connection = $this->getConnection();
        //           \Crowdstar\CouchbaseAdapter\CouchbaseAdapter::getActiveConnection()
        //             \Crowdstar\CouchbaseAdapter\CouchbaseAdapter::reconnect() // Triggered by either a long idle time or a Couchbase exception.
        //               $this->lastUseTime is reset to 0.0 in method \Crowdstar\CouchbaseAdapter\CouchbaseAdapter::close().
        //               $cluster->openBucket($this->config['bucket']); // ASSUME HERE WE FAILED TO OPEN THE BUCKET.
        //   2. In the 2nd HTTP request:
        //      \App\Task\CouchbaseTask::get()
        //         $connection = $this->getConnection();
        //           \Crowdstar\CouchbaseAdapter\CouchbaseAdapter::getActiveConnection()
        //             \Crowdstar\CouchbaseAdapter\CouchbaseAdapter::reconnect() // Couchbase is forced to reconnect since $this->lastUseTime is 0.
        $this->lastUseTime = 0.0;
        unset($this->bucket);
    }

    public function check(): bool
    {
        $now = microtime(true);
        if ($now > ($this->getMaxIdleTime() + $this->lastUseTime)) {
            return false;
        }

        $this->lastUseTime = $now;
        return true;
    }

    public function log(string $level, string $message, array $context = []): self
    {
        $this->logger->log($level, $message, $context);

        return $this;
    }

    /**
     * @see config/autoload/couchbase.php
     * @see docker/rootfilesystem/usr/local/etc/php/conf.d/docker-php-ext-couchbase.ini
     * @see https://github.com/couchbase/php-couchbase/blob/v2.6.2/api/couchbase.php#L47
     */
    protected function getMaxIdleTime(): int
    {
        return $this->config['settings']['max_idle_time'];
    }
}
