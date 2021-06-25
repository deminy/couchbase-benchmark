<?php

declare(strict_types=1);

use App\Controller\IndexController;
use Hyperf\HttpServer\Router\Router;

Router::get('/', [IndexController::class, 'index']);
Router::get('/stats', [IndexController::class, 'stats']);
Router::get('/test', [IndexController::class, 'test']);
Router::get('/shutdown', [IndexController::class, 'shutdown']);
