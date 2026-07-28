<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "=== REMOVING OLD UN-SYNCED PRODUCTS FROM LIVE MYSQL ===\n\n";

$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if (!$r->successful()) {
    echo "❌ Failed to fetch remote products: HTTP " . $r->status() . "\n";
    exit(1);
}

$remoteProducts = collect($r->json('data.products') ?? []);
echo "Total remote products: " . $remoteProducts->count() . "\n";

// Find products with empty sync_uuid
$emptyUuidProducts = $remoteProducts->filter(fn($p) => empty($p['sync_uuid']));
echo "Products with empty sync_uuid on remote server: " . $emptyUuidProducts->count() . "\n";

$deletedPayload = [];
foreach ($emptyUuidProducts as $ep) {
    $deletedPayload[] = [
        'sync_uuid' => $ep['sync_uuid'] ?? '',
        'name' => $ep['name'],
        'record_id' => $ep['id'],
    ];
}

if (!empty($deletedPayload)) {
    echo "Sending PUSH to delete " . count($deletedPayload) . " un-synced products by name...\n";
    $delRes = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->post($pushUrl, [
        'batch_id' => 'DELETE-OLD-EMPTY-' . time(),
        'deleted_products' => $deletedPayload,
    ]);
    echo "Delete Response Status: " . $delRes->status() . "\n";
}

// Check final state
$r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

$finalRemoteProducts = collect($r2->json('data.products') ?? []);
echo "\nFinal active products on Live MySQL:\n";
foreach ($finalRemoteProducts as $fp) {
    echo "  ID: {$fp['id']} | Name: {$fp['name']} | SyncUUID: {$fp['sync_uuid']} | Stock: {$fp['stock_quantity']}\n";
}

echo "\n=== CLEANUP COMPLETE ===\n";
