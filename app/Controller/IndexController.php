<?php

declare(strict_types=1);

namespace App\Controller;

use Couchbase\Exception;
use Crowdstar\OOM\Drivers\Couchbase\StandardDriver;
use CrowdStar\Reflection\Reflection;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Server\ServerFactory;
use Hyperf\Utils\ApplicationContext;
use Monolog\Logger;
use Swoole\Http\Server as SwooleHttpServer;

class IndexController
{
    public function index(): array
    {
        return ['OK'];
    }

    public function shutdown(): array
    {
        /** @var ServerFactory $serverFactory */
        $serverFactory = ApplicationContext::getContainer()->get(ServerFactory::class);
        /** @var SwooleHttpServer $server */
        $server = $serverFactory->getServer()->getServer();
        $server->shutdown();

        return ['DONE'];
    }

    public function stats(): array
    {
        /** @var ServerFactory $serverFactory */
        $serverFactory = ApplicationContext::getContainer()->get(ServerFactory::class);
        /** @var SwooleHttpServer $server */
        $server = $serverFactory->getServer()->getServer();
        $driver = new StandardDriver();

        $stats = [
            'server'    => $server->stats(),
            'couchbase' => $driver->info(),
        ];
        if (!empty($stats['server']['start_time'])) {
            $stats['server']['start_time'] = date('Y-m-d H:i:s', $stats['server']['start_time']);
        }
        if (isset($stats['couchbase']['vBucketServerMap'])) {
            unset($stats['couchbase']['vBucketServerMap']);
        }

        return $stats;
    }

    /**
     * @see \App\Task\CouchbaseTask::get()
     * @see \App\Task\CouchbaseTask::upsert()
     * @see \App\Task\CouchbaseTask::insert()
     * @see \App\Task\CouchbaseTask::replace()
     * @see \App\Task\CouchbaseTask::remove()
     * @see \App\Task\CouchbaseTask::counter()
     * @todo Test method \App\Task\CouchbaseTask::getAndLock()
     */
    public function test(): array
    {
        $logger  = env('VERBOSE_MODE') ? ApplicationContext::getContainer()->get(LoggerFactory::class)->get('test') : new Logger('test');
        $postfix = uniqid('', true) . '-' . rand() . '-' . rand(); // Generate a unique postfix (for benchmark purpose).

        // Couchbase method replace() is tested using $key2 and $key3.
        $key1 = "test-key1{$postfix}"; // Used to test method insert(), get(), and remove().
        $key2 = "test-key2{$postfix}"; // Used to test method upsert().
        $key3 = "test-key3{$postfix}"; // Used to test method counter().

        $driver = new StandardDriver();

        // To clean up existing records.
        // $logger->debug("0. Remove all three keys.");
        // Total # of Couchbase queries: 1.
        $driver->remove([$key1, $key2, $key3]);

        // This is to test method insert().
        $logger->debug('1-1. Insert the first key.');
        // Total # of Couchbase queries: 2.
        $driver->insert($key1, $key1); // PASS; new record
        // Total # of Couchbase queries: 3.
        if ($driver->get($key1) !== $key1) {
            return $this->getErrorResponse('get() should pass.');
        }
        $logger->debug('1-2. Insert the first key again!');
        try {
            $failed = false;
            // Total # of Couchbase queries: 4.
            $driver->insert($key1, ''); // FAIL; existing record
        } catch (Exception $e) {
            if ($e->getCode() === COUCHBASE_KEY_EEXISTS) {
                $failed = true;
            }
        } finally {
            if (!$failed) {
                return $this->getErrorResponse('insert() should fail.');
            }
        }
        $logger->debug('1-3. the first key remains unchanged after a failed insertion (the 2nd insertion).');
        // Total # of Couchbase queries: 5.
        if ($driver->get($key1) !== $key1) {
            return $this->getErrorResponse('get() should fail.');
        }

        // This is to test method upsert().
        $logger->debug('2-1. Update the 2nd key (a new key) using method upsert().');
        // Total # of Couchbase queries: 6.
        $driver->upsert($key2, ''); // PASS; new record
        // Total # of Couchbase queries: 7.
        if ($driver->get($key2) !== '') {
            return $this->getErrorResponse('upsert() should pass.');
        }
        $logger->debug('2-2. Update the 2nd key again (an existing key) using method upsert().');
        // Total # of Couchbase queries: 8.
        $driver->upsert($key2, $key2); // PASS; existing record
        // Total # of Couchbase queries: 9.
        if ($driver->get($key2) !== $key2) {
            return $this->getErrorResponse('upsert() should pass.');
        }

        // This is to test method counter().
        $logger->debug('3-1. Create a new counter key using the 3rd key (a new key).');
        // Total # of Couchbase queries: 10.
        if ($this->counter($driver, $key3) !== 1) {
            return $this->getErrorResponse('counter() should pass when the key has not yet been created.');
        }
        $logger->debug('3-2. Increase the existing counter key (the 3rd key) by 1.');
        // Total # of Couchbase queries: 11.
        if ($this->counter($driver, $key3) !== 2) {
            return $this->getErrorResponse('counter() should pass.');
        }
        $logger->debug('3-3. Increase the existing counter key (the 3rd key) by 2.');
        // Total # of Couchbase queries: 12.
        if ($this->counter($driver, $key3, 2) !== 4) {
            return $this->getErrorResponse('counter() should pass.');
        }
        $logger->debug('3-4. Use a regular key (the 2nd key) as a counter key.');
        try {
            $failed = false;
            // Total # of Couchbase queries: 13.
            $this->counter($driver, $key2); // FAIL; bad value
        } catch (Exception $e) {
            if ($e->getCode() === COUCHBASE_DELTA_BADVAL) {
                $failed = true;
            }
        } finally {
            if (!$failed) {
                return $this->getErrorResponse('counter() should fail.');
            }
        }

        // This is to test method get() and remove().
        $logger->debug('4-1. Get the first key (an existing key).');
        // Total # of Couchbase queries: 14.
        if ($driver->get($key1) !== $key1) { // PASS; existing record
            return $this->getErrorResponse('get() should pass.');
        }
        $logger->debug('5-1. Delete the first key (an existing key).');
        // Total # of Couchbase queries: 15.
        $driver->remove($key1); // PASS; non-existing record
        $logger->debug('4-2. Get the first key (a non-existing key).');
        // Total # of Couchbase queries: 16.
        if ($driver->get($key1) !== null) { // FAIL; non-existing record
            return $this->getErrorResponse('get() should fail.');
        }
        $logger->debug('5-2. Delete the first key (a non-existing key).');
        // Total # of Couchbase queries: 17.
        $driver->remove($key1); // FAIL; non-existing record

        $logger->debug('6-1. Replace the 2nd key (an existing key).');
        // Total # of Couchbase queries: 18.
        if ($driver->get($key2) !== $key2) { // To make sure $key2 contains an expected value.
            return $this->getErrorResponse('get() should fail.');
        }
        // Total # of Couchbase queries: 19.
        $driver->replace($key2, "{$key2}-6.1"); // PASS; existing record
        // Total # of Couchbase queries: 20.
        if ($driver->get($key2) !== "{$key2}-6.1") { // To make sure $key2 has been updated.
            return $this->getErrorResponse('get() should fail.');
        }
        // Total # of Couchbase queries: 21.
        $driver->remove($key3);
        $logger->debug('6-2. Replace the 3nd key (a non-existing key).');
        try {
            $failed = false;
            // Total # of Couchbase queries: 22.
            $driver->replace($key3, "{$key3}-6.2"); // FAIL; non-existing record
        } catch (Exception $e) {
            if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                $failed = true;
            }
        } finally {
            if (!$failed) {
                return $this->getErrorResponse('counter() should fail.');
            }
        }
        // Total # of Couchbase queries: 23.
        if ($driver->get($key3) !== null) { // To make sure $key3 not been updated.
            return $this->getErrorResponse('get() should fail in 6.2.');
        }

        // To clean up existing records.
        // Total # of Couchbase queries: 24.
        $driver->remove([$key1, $key2, $key3]);

        return ['OK'];
    }

    protected function counter(StandardDriver $driver, string $key, int $delta = 1)
    {
        return Reflection::callMethod($driver, 'counter', [$key, $delta]);
    }

    protected function getErrorResponse(string $message): array
    {
        return ['error' => $message];
    }
}
