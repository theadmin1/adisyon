<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
    } elseif (!empty($_SERVER['SERVER_NAME'])) {
        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'];
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'staff.permission' => \App\Http\Middleware\EnsureStaffModulePermission::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'api/v1/*',
            'api/v1/license/verify',
            'api/v1/device/ping',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

