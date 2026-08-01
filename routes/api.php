<?php

use App\Http\Controllers\Api\Waiter\AuthController;
use App\Http\Controllers\Api\Waiter\KitchenController;
use App\Http\Controllers\Api\Waiter\OrderController;
use App\Http\Controllers\Api\Waiter\PaymentController;
use App\Http\Controllers\Api\Waiter\ProductController;
use App\Http\Controllers\Api\Waiter\RealtimeController;
use App\Http\Controllers\Api\Waiter\TableController;
use App\Http\Middleware\CaptureApiTraffic;
use Illuminate\Support\Facades\Route;

Route::middleware(CaptureApiTraffic::class)->prefix('v1/waiter')->name('api.waiter.')->group(function (): void {
    Route::middleware('throttle:waiter-api-auth')->prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/profiles', [AuthController::class, 'profiles'])->name('profiles');
        Route::post('/login', [AuthController::class, 'login'])->name('login');
    });

    Route::middleware(['waiter.api', 'throttle:waiter-api'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/realtime/config', [RealtimeController::class, 'config'])->name('realtime.config');
        Route::post('/realtime/auth', [RealtimeController::class, 'authenticate'])->name('realtime.auth');

        Route::get('/halls', [TableController::class, 'halls'])->name('halls.index');
        Route::get('/tables', [TableController::class, 'index'])->name('tables.index');
        Route::get('/tables/{table}', [TableController::class, 'show'])->whereNumber('table')->name('tables.show');

        Route::get('/categories', [ProductController::class, 'categories'])->name('categories.index');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::post('/orders/{order}/items', [OrderController::class, 'addItems'])->whereNumber('order')->name('orders.items.store');
        Route::delete('/orders/{order}/items/{item}', [OrderController::class, 'removeItem'])->whereNumber(['order', 'item'])->name('orders.items.destroy');
        Route::patch('/orders/{order}/notes', [OrderController::class, 'updateNotes'])->whereNumber('order')->name('orders.notes.update');
        Route::post('/orders/{order}/send-kitchen', [OrderController::class, 'sendToKitchen'])->whereNumber('order')->name('orders.send-kitchen');
        Route::post('/orders/{order}/request-payment', [OrderController::class, 'requestPayment'])->whereNumber('order')->name('orders.request-payment');

        Route::get('/kitchen/updates', [KitchenController::class, 'updates'])->name('kitchen.updates');
        Route::post('/kitchen/items/{item}/served', [KitchenController::class, 'markServed'])->whereNumber('item')->name('kitchen.items.served');

        Route::get('/orders/{order}/payments', [PaymentController::class, 'index'])->whereNumber('order')->name('payments.index');
        Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->whereNumber('order')->name('payments.store');
    });
});
