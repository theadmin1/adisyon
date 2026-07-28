<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "=== MERGING & CLEANING LIVE MYSQL PRODUCTS ===\n\n";

$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if (!$r->successful()) {
    echo "❌ Failed to fetch remote products\n";
    exit(1);
}

$remoteProducts = collect($r->json('data.products') ?? []);
echo "Current total remote products: " . $remoteProducts->count() . "\n";

// 1. Delete duplicate products with IDs > 148
$duplicateUuids = [];
foreach ($remoteProducts as $rp) {
    if ($rp['id'] > 148) {
        $duplicateUuids[] = [
            'sync_uuid' => $rp['sync_uuid'],
            'name' => $rp['name'],
            'record_id' => $rp['id'],
        ];
    }
}

if (!empty($duplicateUuids)) {
    echo "Deleting " . count($duplicateUuids) . " duplicate product rows (>148) on remote server...\n";
    $delRes = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->post($pushUrl, [
        'batch_id' => 'DEL-DUPS-' . time(),
        'deleted_products' => $duplicateUuids,
    ]);
    echo "Delete Status: " . $delRes->status() . "\n";
}

// 2. Re-fetch remaining products (IDs 133..148)
$r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

$remoteProducts2 = collect($r2->json('data.products') ?? []);
echo "Remaining remote products count: " . $remoteProducts2->count() . "\n";

// 3. Update products 133..148 with SQLite's sync_uuid and current decremented stock
$sqliteProducts = Product::all();
$updatePayload = [];

foreach ($remoteProducts2 as $rp) {
    $localProd = $sqliteProducts->firstWhere('name', $rp['name']);
    if ($localProd) {
        $updatePayload[] = [
            'id' => $rp['id'],
            'sync_uuid' => $localProd->sync_uuid,
            'name' => $localProd->name,
            'price' => (float) $localProd->price,
            'stock_quantity' => (float) $localProd->stock_quantity,
            'track_stock' => (bool) $localProd->track_stock,
            'is_active' => (bool) $localProd->is_active,
        ];
    }
}

echo "Updating " . count($updatePayload) . " primary remote products (IDs 133..148) with valid sync_uuids and stock...\n";
$upRes = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, [
    'batch_id' => 'UPDATE-MAIN-' . time(),
    'products' => $updatePayload,
]);
echo "Update Status: " . $upRes->status() . "\n";

// 4. Verify Final State
$rFinal = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

$finalProds = collect($rFinal->json('data.products') ?? []);
echo "\nFINAL LIVE MYSQL PRODUCTS:\n";
foreach ($finalProds as $fp) {
    echo "  ID: {$fp['id']} | Name: {$fp['name']} | SyncUUID: {$fp['sync_uuid']} | Stock: {$fp['stock_quantity']}\n";
}

echo "\n=== MERGE & CLEAN COMPLETE ===\n";
