#!/usr/bin/env php
<?php

declare(strict_types=1);

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

error_reporting(E_ALL);

!defined('BASE_PATH') && define('BASE_PATH', __DIR__);
!defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL & SWOOLE_HOOK_CURL & ~SWOOLE_HOOK_NATIVE_CURL);

require BASE_PATH . '/vendor/autoload.php';

use Hyperf\Contract\ApplicationInterface;
use Hyperf\Di\ClassLoader;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;

// Self-called anonymous function that creates its own scope and keep the global namespace clean.
(function () {
    ClassLoader::init();
    /** @var ContainerInterface $container */
    $container = require BASE_PATH . '/config/container.php';

    /** @var Application $application */
    $application = $container->get(ApplicationInterface::class);
    $application->run();
})();
