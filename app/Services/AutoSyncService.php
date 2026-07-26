<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AutoSyncService
{
    /**
     * Uygulama yerel (SQLite) modda çalışırken verileri anlık olarak 
     * canlı MySQL sunucusuyla (adisyon.synaptropic.com) PUSH ve PULL eder.
     */
    public static function syncIfLocal(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                Artisan::call('app:sync-local');
            } catch (\Throwable $e) {
                Log::channel('sync')->warning('[AUTO-SYNC] Arka plan senkronizasyon uyarısı: ' . $e->getMessage());
            }
        }
    }
}
