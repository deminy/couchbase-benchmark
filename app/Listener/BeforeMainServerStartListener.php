<?php

declare(strict_types=1);

namespace App\Listener;

use App\Service\CouchbaseProxyInterface;
use Crowdstar\CouchbaseAdapter\CouchbaseAdapter;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Hyperf\Utils\ApplicationContext;

class BeforeMainServerStartListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BeforeMainServerStart::class,
        ];
    }

    /**
     * @param BeforeMainServerStart $event
     */
    public function process(object $event): void
    {
        /** @var CouchbaseAdapter $couchbaseAdapter */
        $couchbaseAdapter = ApplicationContext::getContainer()->get(CouchbaseProxyInterface::class);
        $logger           = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);

        $start = time();
        while (true) {
            try {
                $couchbaseAdapter->getActiveConnection();
            } catch (\Throwable $t) {
                if ((time() - $start) < 60) {
                    $logger->notice('Unable to connect to Couchbase. Will try again in 2 seconds.');
                    sleep(2);
                    continue;
                }
                $logger->error('Unable to connect to Couchbase after 60 seconds. Stopping server.');
                exit(-1);
            }
            $couchbaseAdapter->close();
            $logger->info('The Couchbase server is reachable. Starting the HTTP server.');
            break;
        }
    }
}
