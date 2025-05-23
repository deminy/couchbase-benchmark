<?php

declare(strict_types=1);

namespace Crowdstar\CouchbaseAdapter;

use Crowdstar\Couchbase3\Bucket;
use Crowdstar\Couchbase3\Cluster;
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

    /**
     * The connection string to Couchbase.
     */
    protected string $connstr;

    protected Bucket $bucket;

    protected float $lastUseTime = 0.0;

    protected LoggerInterface $logger;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;

        $this->initConnectionString();
    }

    /**
     * Execute a Couchbase command after getting an active connection with method $this->getActiveConnection().
     *
     * @see CouchbaseAdapter::getActiveConnection()
     */
    public function __call(string $name, array $arguments)
    {
        return $this->bucket->{$name}(...$arguments);
    }

    /**
     * @throws \CouchbaseException
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
     * @throws \CouchbaseException
     */
    public function reconnect(): bool
    {
        $this->logger->debug('Connecting to Couchbase.');

        $this->close();

        // TODO: handle connection failures.
        $cluster = new Cluster($this->connstr);
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

    /**
     * Initialize the connection string to Couchbase.
     */
    private function initConnectionString(): void
    {
        ini_set('couchbase.log_level', $this->config['log_level']);

        if (!empty($this->config['options'])) {
            parse_str($this->config['options'], $options);
            if (!empty($options['truststorepath'])) {
                if (!is_readable($options['truststorepath'])) {
                    throw new Exception("Unable to access the certificate file \"{$options['truststorepath']}\" for connecting to Couchbase.");
                }
                if ($this->config['protocol'] !== 'couchbases') {
                    throw new Exception('Only protocol "couchbases" can be used when option "truststorepath" is include.');
                }
                if (!empty($options['ssl']) && ($options['ssl'] === 'no_verify')) {
                    $this->logger->warning('Certificate verification for SSL is disabled.');
                }
            }
        }

        $this->connstr = "{$this->config['protocol']}://{$this->config['host']}?detailed_errcodes=1";
        if (!empty($options)) {
            $this->connstr .= '&' . http_build_query($options);
        }
        $this->log('debug', "Couchbase connection string: {$this->connstr}");
    }
}
