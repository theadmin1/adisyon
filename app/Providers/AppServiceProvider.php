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
        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'off' || empty($_SERVER['HTTPS']))) {
            Config::set('session.secure', false);
        }

        // 2. Kararlı Veritabanı Yapılandırması (Rasgele MySQL flip-flop geçişlerini engelle)
        if (env('DB_CONNECTION', 'sqlite') === 'sqlite' || isset($_SERVER['HTTP_HOST']) && (str_contains($_SERVER['HTTP_HOST'], '127.0.0.1') || str_contains($_SERVER['HTTP_HOST'], 'localhost'))) {
            Config::set('database.default', 'sqlite');
            Config::set('session.driver', 'file');
            Config::set('cache.default', 'file');
            Config::set('queue.default', 'sync');
        } else {
            try {
                if (Config::get('database.default') === 'mysql') {
                    Config::set('database.connections.mysql.options.' . \PDO::ATTR_TIMEOUT, 1);
                    DB::connection('mysql')->getPdo();
                }
            } catch (Throwable $e) {
                Config::set('database.default', 'sqlite');
                Config::set('session.driver', 'file');
                Config::set('cache.default', 'file');
                Config::set('queue.default', 'sync');
                DB::purge();
            }
        }

        // 3. Eğer aktif veritabanı SQLite ise veritabanı dosyasını ve tablolarını hazırla
        if (Config::get('database.default') === 'sqlite') {
            try {
                $sqlitePath = config('database.connections.sqlite.database');
                if (!file_exists($sqlitePath)) {
                    @touch($sqlitePath);
                }
                if (!\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('users')) {
                    \Illuminate\Support\Facades\Artisan::call('migrate', [
                        '--database' => 'sqlite',
                        '--force' => true
                    ]);
                }
                if (\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('users') && \App\Models\User::on('sqlite')->count() === 0) {
                    \Illuminate\Support\Facades\Artisan::call('db:seed', [
                        '--database' => 'sqlite',
                        '--force' => true
                    ]);
                }
            } catch (Throwable $e) {
                // Sessizce logla ama uygulamanın çökmesini engelle
                \Illuminate\Support\Facades\Log::warning('SQLite otomatik ilklendirme uyarısı: ' . $e->getMessage());
            }
        }
    }
}
