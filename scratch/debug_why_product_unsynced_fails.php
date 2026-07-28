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

echo "=== DEBUG PUSH UN-SYNCED PRODUCT STEP-BY-STEP ===\n\n";

$p = Product::find(1);
echo "1. BEFORE SALE: ID={$p->id}, Stock={$p->stock_quantity}, is_synced=" . var_export($p->is_synced, true) . "\n";

// Add item
$checkService = app(\App\Services\Checks\CheckService::class);
$table = \App\Models\DiningTable::first();
$check = $checkService->openCheck($table);
$checkService->addItems($check, [['product_id' => $p->id, 'quantity' => 1]]);

$pAfter = Product::find(1);
echo "2. AFTER ADD ITEMS: Stock={$pAfter->stock_quantity}, is_synced=" . var_export($pAfter->is_synced, true) . "\n";

$unsyncedProds = DB::connection('sqlite')->table('products')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
echo "3. UNSYNCED PRODUCTS COUNT: " . $unsyncedProds->count() . "\n";
foreach ($unsyncedProds as $up) {
    echo "   - ID: {$up->id}, Name: {$up->name}, Stock: {$up->stock_quantity}, is_synced: " . var_export($up->is_synced, true) . "\n";
}

// Build payload
$productsPayload = [];
foreach ($unsyncedProds as $prod) {
    $categorySyncUuid = DB::connection('sqlite')->table('categories')->where('id', $prod->category_id)->value('sync_uuid');
    $productsPayload[] = [
        'id' => $prod->id,
        'sync_uuid' => $prod->sync_uuid,
        'category_id' => $prod->category_id,
        'category_sync_uuid' => $categorySyncUuid,
        'name' => $prod->name,
        'slug' => $prod->slug,
        'price' => (float) $prod->price,
        'stock_quantity' => (float) $prod->stock_quantity,
        'track_stock' => (bool) $prod->track_stock,
        'is_active' => (bool) $prod->is_active,
    ];
}

$payload = [
    'batch_id' => 'DEBUG-MANUAL-' . time(),
    'products' => $productsPayload,
];

echo "4. PUSHING PAYLOAD TO REMOTE SERVER...\n";
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, $payload);

echo "   HTTP Status: " . $r->status() . "\n";
echo "   Response Body: " . $r->body() . "\n";

if ($r->successful()) {
    $syncedUuids = $r->json('synced_uuids') ?? [];
    echo "5. SYNCED UUIDs RETURNED FROM SERVER: " . implode(', ', $syncedUuids) . "\n";
    if (!empty($syncedUuids)) {
        DB::connection('sqlite')->table('products')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
    }
}

$pFinal = Product::find(1);
echo "6. FINAL SQLITE PRODUCT STATE: Stock={$pFinal->stock_quantity}, is_synced=" . var_export($pFinal->is_synced, true) . "\n";

// Close check cleanup
$checkService->closeCheck($check);
