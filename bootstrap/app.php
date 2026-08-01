<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureDeviceApiKey;
use App\Http\Middleware\EnsureChainUser;
use App\Http\Middleware\EnsureRestaurantUser;
use App\Http\Middleware\EnsureStaffModulePermission;
use App\Http\Middleware\EnsureWaiterApiToken;
use App\Http\Middleware\VerifyDeliveryWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ?: null);
        $middleware->alias([
            'staff.permission' => EnsureStaffModulePermission::class,
            'device.api' => EnsureDeviceApiKey::class,
            'restaurant.user' => EnsureRestaurantUser::class,
            'chain.user' => EnsureChainUser::class,
            'webhook.signature' => VerifyDeliveryWebhookSignature::class,
            'waiter.api' => EnsureWaiterApiToken::class,
        ]);
        $middleware->append(AddSecurityHeaders::class);
        // CSRF muafiyeti YALNIZCA cihaz (C# servisi) uçlarına verilir.
        // Tarayıcıdan çağrılan api/v1/print/* uçları CSRF korumalı kalır.
        $middleware->validateCsrfTokens(except: [
            'license/verify',
            'api/license/verify',
            'api/v1/license/verify',
            'api/v1/device/ping',
            'api/v1/sync/push',
            'api/v1/sync/pull',
            'api/v1/print/pending',
            'api/v1/print/jobs/*/claim',
            'api/v1/print/jobs/*/status',
            'api/v1/print/printers',
            'api/v1/integrations/trendyol-go/webhook',
            'api/v1/integrations/yemeksepeti/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
