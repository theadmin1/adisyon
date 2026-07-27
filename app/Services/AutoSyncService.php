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
                $artisan = base_path('artisan');
                if (str_contains(PHP_OS_FAMILY, 'Windows')) {
                    $cmd = 'cmd /c "php "' . $artisan . '" app:sync-local > NUL 2>&1"';
                    @pclose(@popen($cmd, 'r'));
                } else {
                    $cmd = 'php "' . $artisan . '" app:sync-local > /dev/null 2>&1 &';
                    @exec($cmd);
                }
            } catch (\Throwable $e) {
                Log::channel('sync')->warning('[AUTO-SYNC] Arka plan senkronizasyon tetikleme uyarısı: ' . $e->getMessage());
            }
        }
    }
}
