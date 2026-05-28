<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Request;
use App\Core\Router;

$router = new Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch(Request::capture());
