<?php

declare(strict_types=1);

namespace App\Listener;

use App\Task\CouchbaseTask;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Swoole\Coroutine;

class CouchbaseListener implements ListenerInterface
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
    public function process(object $event)
    {
        if (Coroutine::getPcid() === false) { // Task worker
            (new CouchbaseTask())->info();
        }
    }
}
