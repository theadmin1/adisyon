<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AutoSyncService
{
    /**
     * Uygulama yerel (SQLite) modda çalışırken verileri arka planda
     * SAYFA YÜKLENMESİNİ YAVAŞLATMADAN (asenkron non-blocking) 
     * canlı MySQL sunucusuyla (adisyon.synaptropic.com) PUSH ve PULL eder.
     */
    public static function syncIfLocal(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                $basePath = base_path();
                if (str_contains(PHP_OS_FAMILY, 'Windows')) {
                    // Windows ortamında web isteğini yavaşlatmadan arka planda çalıştır
                    @pclose(@popen("cd /d \"{$basePath}\" && start /B php artisan app:sync-local > NUL 2>&1", "r"));
                } else {
                    // Linux ortamında arka planda çalıştır
                    @exec("cd \"{$basePath}\" && php artisan app:sync-local > /dev/null 2>&1 &");
                }
            } catch (\Throwable $e) {
                Log::channel('sync')->warning('[AUTO-SYNC] Arka plan senkronizasyon tetikleme uyarısı: ' . $e->getMessage());
            }
        }
    }
}
