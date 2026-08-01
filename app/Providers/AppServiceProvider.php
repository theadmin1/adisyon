<?php

namespace App\Providers;

use App\Observers\CriticalModelObserver;
use App\Observers\OfflineSyncObserver;
use Illuminate\Support\Facades\Config;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiters();

        foreach (CriticalModelObserver::observedModels() as $model) {
            $model::observe(CriticalModelObserver::class);
        }

        foreach (OfflineSyncObserver::observedModels() as $model) {
            $model::observe(OfflineSyncObserver::class);
        }

        if (config('adisyon.offline_mode')) {
            Config::set('database.default', 'sqlite');
            Config::set('session.driver', 'file');
            Config::set('cache.default', 'file');
            Config::set('queue.default', 'sync');
        }
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('restaurant-login', fn (Request $request) => Limit::perMinute(8)->by(
            $this->rateLimitKey('restaurant-login', $request->input('restaurant_id'), $request->ip())
        ));

        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(5)->by(
            $this->rateLimitKey('admin-login', $request->input('email'), $request->ip())
        ));

        RateLimiter::for('chain-login', fn (Request $request) => Limit::perMinute(5)->by(
            $this->rateLimitKey('chain-login', $request->input('email'), $request->ip())
        ));

        RateLimiter::for('staff-select', fn (Request $request) => Limit::perMinute(10)->by(
            $this->rateLimitKey('staff-select', $request->user()?->getAuthIdentifier(), $request->ip())
        ));

        RateLimiter::for('supplier-portal', fn (Request $request) => Limit::perMinute(60)->by(
            $this->rateLimitKey('supplier-portal', $request->route('token'), $request->ip(), $request->method())
        ));

        RateLimiter::for('supplier-verify', fn (Request $request) => Limit::perMinute(5)->by(
            $this->rateLimitKey('supplier-verify', $request->route('token'), $request->ip())
        ));

        RateLimiter::for('supplier-submit', fn (Request $request) => Limit::perMinute(20)->by(
            $this->rateLimitKey('supplier-submit', $request->route('token'), $request->ip())
        ));

        RateLimiter::for('license-verify', fn (Request $request) => Limit::perMinute(30)->by(
            $this->rateLimitKey('license-verify', $request->input('license_key'), $request->input('device_guid'), $request->ip())
        ));

        RateLimiter::for('device-heartbeat', fn (Request $request) => Limit::perMinute(120)->by(
            $this->rateLimitKey('device-heartbeat', $request->header('X-Device-Api-Key'), $request->input('device_guid'), $request->ip())
        ));

        RateLimiter::for('device-sync', fn (Request $request) => Limit::perMinute(30)->by(
            $this->rateLimitKey('device-sync', $request->header('X-Device-Api-Key'), $request->path(), $request->ip())
        ));

        RateLimiter::for('delivery-webhook', fn (Request $request) => Limit::perMinute(120)->by(
            $this->rateLimitKey(
                'delivery-webhook',
                $request->header('X-Store-Id') ?: $request->input('storeId') ?: $request->input('restaurantId'),
                $request->path(),
                $request->ip()
            )
        ));

        RateLimiter::for('device-update', fn (Request $request) => Limit::perMinute(10)->by(
            $this->rateLimitKey('device-update', $request->header('X-Device-Api-Key'), $request->path(), $request->ip())
        ));
    }

    private function rateLimitKey(mixed ...$parts): string
    {
        $normalized = array_map(
            static fn (mixed $part): string => strtolower(trim((string) $part)),
            $parts
        );

        return hash('sha256', implode('|', $normalized));
    }
}
