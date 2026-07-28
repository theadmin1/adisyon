<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AutoSyncService
{
    private static bool $scheduled = false;

    /**
     * Cooldown süresi (saniye). Her tıklamada arka planda yeni PHP süreci
     * ve HTTP isteği başlatarak SQLite'ı kilitlemesini ve sayfayı yavaşlatmasını önler.
     */
    private const COOLDOWN_SECONDS = 30;

    public static function syncIfLocal(bool $force = false): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        if (self::$scheduled) {
            return;
        }

        if (! $force) {
            try {
                $lastSync = (int) (\Illuminate\Support\Facades\Cache::get('auto_sync_last_run_timestamp', 0));
                if (time() - $lastSync < self::COOLDOWN_SECONDS) {
                    return;
                }
            } catch (\Throwable) {
            }
        }

        self::$scheduled = true;
        try {
            \Illuminate\Support\Facades\Cache::put('auto_sync_last_run_timestamp', time(), self::COOLDOWN_SECONDS);
        } catch (\Throwable) {
        }

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
