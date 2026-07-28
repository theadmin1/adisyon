<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

$unsyncedStockMovements = DB::connection('sqlite')->table('stock_movements')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
echo "Unsynced Stock Movements: " . $unsyncedStockMovements->count() . "\n";

$unsyncedProducts = DB::connection('sqlite')->table('products')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
echo "Unsynced Products: " . $unsyncedProducts->count() . "\n";
foreach ($unsyncedProducts as $p) {
    echo "  - Product ID: {$p->id} | Name: {$p->name} | Stock: {$p->stock_quantity} | SyncUUID: {$p->sync_uuid}\n";
}

// Build payload
$productsPayload = [];
foreach ($unsyncedProducts as $prod) {
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

$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
$payload = [
    'batch_id' => 'DEBUG-PUSH-' . time(),
    'products' => $productsPayload,
];

echo "\nPOSTing to " . $pushUrl . "...\n";
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, $payload);

echo "Response Status: " . $r->status() . "\n";
echo "Response Body: " . $r->body() . "\n";
