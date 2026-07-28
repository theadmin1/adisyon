<?php

namespace App\Providers;

use App\Observers\CriticalModelObserver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (CriticalModelObserver::observedModels() as $model) {
            $model::observe(CriticalModelObserver::class);
        }

        if (config('adisyon.offline_mode')) {
            Config::set('database.default', 'sqlite');
            Config::set('session.driver', 'file');
            Config::set('cache.default', 'file');
            Config::set('queue.default', 'sync');
        }
    }
}
