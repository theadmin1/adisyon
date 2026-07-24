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

        // 2. Otomatik Veritabanı Failover (İnternet kesikse veya uzak MySQL erişilemezse anında SQLite'a geç)
        try {
            if (Config::get('database.default') === 'mysql') {
                // PDO Zaman aşımını kısa tut ki sayfa yüklemesi takılmasın
                Config::set('database.connections.mysql.options.' . \PDO::ATTR_TIMEOUT, 1);
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
