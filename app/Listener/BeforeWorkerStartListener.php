<?php

declare(strict_types=1);

namespace App\Listener;

use App\Service\CouchbaseProxyInterface;
use Crowdstar\CouchbaseAdapter\CouchbaseAdapter;
use Hyperf\Context\ApplicationContext;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Swoole\Coroutine;

class BeforeWorkerStartListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BeforeWorkerStart::class,
        ];
    }

    /**
     * @param BeforeWorkerStart $event
     */
    public function process(object $event): void
    {
        if (Coroutine::getPcid() === false) { // Task worker
            /** @var CouchbaseAdapter $couchbaseAdapter */
            $couchbaseAdapter = ApplicationContext::getContainer()->get(CouchbaseProxyInterface::class);
            $couchbaseAdapter->getActiveConnection();
        }
    }
}
