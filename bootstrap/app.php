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
            'device.api' => \App\Http\Middleware\EnsureDeviceApiKey::class,
        ]);
        // CSRF muafiyeti YALNIZCA cihaz (C# servisi) uçlarına verilir.
        // Tarayıcıdan çağrılan api/v1/print/* uçları CSRF korumalı kalır.
        $middleware->validateCsrfTokens(except: [
            'api/v1/license/verify',
            'api/v1/device/ping',
            'api/v1/print/pending',
            'api/v1/print/jobs/*/claim',
            'api/v1/print/jobs/*/status',
            'api/v1/print/printers',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

