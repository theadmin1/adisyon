<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Fix HTTPS & Host headers for reverse proxies (Coolify / OpenLiteSpeed / Nginx)
if (
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443)
) {
    $_SERVER['HTTPS'] = 'on';
}

if (isset($_SERVER['HTTP_HOST']) && (str_contains($_SERVER['HTTP_HOST'], '$') || !preg_match('/^[a-zA-Z0-9.-]+(:\d+)?$/', $_SERVER['HTTP_HOST']))) {
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());

