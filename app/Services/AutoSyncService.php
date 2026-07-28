<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AutoSyncService
{
    private static bool $scheduled = false;

    /**
     * Uygulama yerel (SQLite) modda çalışırken verileri arka planda
     * SAYFA YÜKLENMESİNİ YAVAŞLATMADAN (asenkron non-blocking)
     * canlı MySQL sunucusuyla (adisyon.synaptropic.com) PUSH ve PULL eder.
     *
     * İstek döngüsünde (HTTP Request) yalnızca 1 kez kaydedilir ve
     * tüm DB işlemleri/transaction'ları tamamlanıp yanıt gönderildikten
     * (shutdown) sonra tekil olarak tetiklenir.
     */
    public static function syncIfLocal(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        if (self::$scheduled) {
            return;
        }

        self::$scheduled = true;

        register_shutdown_function(function () {
            try {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }

                $artisan = base_path('artisan');
                if (str_contains(PHP_OS_FAMILY, 'Windows')) {
                    $cmd = 'cmd /c "php "'.$artisan.'" app:sync-local > NUL 2>&1"';
                    @pclose(@popen($cmd, 'r'));
                } else {
                    $cmd = 'php "'.$artisan.'" app:sync-local > /dev/null 2>&1 &';
                    @exec($cmd);
                }
            } catch (\Throwable $e) {
                Log::channel('sync')->warning('[AUTO-SYNC] Arka plan senkronizasyon tetikleme uyarısı: '.$e->getMessage());
            }
        });
    }

    /**
     * İstenirse doğrudan anlık çalıştırma (örn: CLI komutlarından veya testlerden)
     */
    public static function syncNow(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                $artisan = base_path('artisan');
                if (str_contains(PHP_OS_FAMILY, 'Windows')) {
                    $cmd = 'cmd /c "php "'.$artisan.'" app:sync-local > NUL 2>&1"';
                    @pclose(@popen($cmd, 'r'));
                } else {
                    $cmd = 'php "'.$artisan.'" app:sync-local > /dev/null 2>&1 &';
                    @exec($cmd);
                }
            } catch (\Throwable $e) {
                Log::channel('sync')->warning('[AUTO-SYNC] Anlık senkronizasyon tetikleme uyarısı: '.$e->getMessage());
            }
        }
    }
}
