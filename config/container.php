<?php

declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;

$container = new Container((new DefinitionSourceFactory(true))());

if (!$container instanceof Psr\Container\ContainerInterface) {
    throw new RuntimeException('The dependency injection container is invalid.');
}
return ApplicationContext::setContainer($container);
