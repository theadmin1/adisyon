<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. HTTP isteklerinde (localhost/127.0.0.1) Secure Cookie zorunluluğunu esnet
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'off' || empty($_SERVER['HTTPS'])) {
            Config::set('session.secure', false);
        }

        // 2. Otomatik Veritabanı Failover (İnternet kesikse veya uzak MySQL erişilemezse anında SQLite'a geç)
        try {
            // Aktif veritabanı sürücüsü mysql ise bağlantıyı hızlıca sına
            if (Config::get('database.default') === 'mysql') {
                DB::connection('mysql')->getPdo();
            }
        } catch (Throwable $e) {
            // Uzak MySQL erişilemez (İnternet kesik veya zaman aşımı)! Anında yerel SQLite'a düş.
            Config::set('database.default', 'sqlite');
            Config::set('session.driver', 'file');
            Config::set('cache.default', 'file');
            Config::set('queue.default', 'sync');
            DB::purge();
        }
    }
}
