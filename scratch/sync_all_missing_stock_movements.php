<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "=== RE-SYNCING ALL HISTORICAL STOCK MOVEMENTS TO MYSQL ===\n\n";

// 1. Fetch remote stock movements from MySQL
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if (!$r->successful()) {
    echo "❌ Failed to fetch remote data\n";
    exit(1);
}

$remoteMovements = collect($r->json('data.stock_movements') ?? []);
$remoteUuids = $remoteMovements->pluck('sync_uuid')->filter()->toArray();

echo "Remote Stock Movements count: " . count($remoteUuids) . "\n";

// 2. Fetch SQLite stock movements
$localMovements = DB::connection('sqlite')->table('stock_movements')->get();
echo "Local SQLite Stock Movements count: " . $localMovements->count() . "\n";

// Mark any local stock movement whose sync_uuid is NOT on MySQL as is_synced = 0
$unpushedCount = 0;
foreach ($localMovements as $lm) {
    if (!empty($lm->sync_uuid) && !in_array($lm->sync_uuid, $remoteUuids, true)) {
        DB::connection('sqlite')->table('stock_movements')->where('id', $lm->id)->update(['is_synced' => 0]);
        $unpushedCount++;
    }
}

echo "Marked {$unpushedCount} local stock movements as unsynced (is_synced = 0).\n\n";

// 3. Run app:sync-local to PUSH all missing stock movements to MySQL
echo "Running app:sync-local...\n";
Illuminate\Support\Facades\Artisan::call('app:sync-local');

// 4. Re-check remote stock movements
$r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

$remoteMovementsAfter = collect($r2->json('data.stock_movements') ?? []);
echo "Final Remote Stock Movements count on MySQL: " . $remoteMovementsAfter->count() . "\n";

echo "\n=== RE-SYNC FINISHED ===\n";
