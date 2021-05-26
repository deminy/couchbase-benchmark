<?php

declare(strict_types=1);

use App\Controller\IndexController;
use Hyperf\HttpServer\Router\Router;

Router::get('/', [IndexController::class, 'index']);
Router::get('test-couchbase', [IndexController::class, 'test']);
