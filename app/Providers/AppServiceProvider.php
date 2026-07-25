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

        // 2. Yerel Kasa (127.0.0.1 / Localhost) için SQLite, Canlı Bulut için .env Veritabanını Kullan
        $isLocalhostRequest = isset($_SERVER['HTTP_HOST']) && (str_contains($_SERVER['HTTP_HOST'], '127.0.0.1') || str_contains($_SERVER['HTTP_HOST'], 'localhost'));

        if ($isLocalhostRequest) {
            Config::set('database.default', 'sqlite');
            Config::set('session.driver', 'file');
            Config::set('cache.default', 'file');
            Config::set('queue.default', 'sync');
        } else {
            if (Config::get('database.default') === 'mysql') {
                $host = Config::get('database.connections.mysql.host');
                if (($host === '127.0.0.1' || $host === 'localhost') && (file_exists('/.dockerenv') || file_exists('/run/.containerenv'))) {
                    Config::set('database.connections.mysql.host', '172.17.0.1');
                }
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
