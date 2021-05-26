<?php

declare(strict_types=1);

namespace App\Service;

use Crowdstar\CouchbaseAdapter\CouchbaseAdapter;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;

class CouchbaseProxyFactory
{
    /**
     * This callback returns a persistent CouchbaseAdapter object.
     *
     * @return CouchbaseAdapter
     */
    public function __invoke(ContainerInterface $container)
    {
        return new CouchbaseAdapter(
            $container->get(ConfigInterface::class)->get('couchbase')['default'],
            $container->get(LoggerFactory::class)->get('couchbase')
        );
    }
}
