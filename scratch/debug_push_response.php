<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';

echo "=== DEBUGGING PUSH RESPONSE FROM LIVE SERVER ===\n\n";

$unsyncedStockMovements = DB::connection('sqlite')->table('stock_movements')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
$unsyncedProducts = DB::connection('sqlite')->table('products')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();

echo "Unsynced Stock Movements count: " . $unsyncedStockMovements->count() . "\n";
echo "Unsynced Products count: " . $unsyncedProducts->count() . "\n";

$stockPayload = [];
foreach ($unsyncedStockMovements as $stock) {
    $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $stock->product_id)->value('sync_uuid');
    $pName = DB::connection('sqlite')->table('products')->where('id', $stock->product_id)->value('name');
    $stockPayload[] = [
        'sync_uuid' => $stock->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
        'product_id' => $stock->product_id,
        'product_sync_uuid' => $pSyncUuid,
        'product_name' => $pName,
        'type' => $stock->type,
        'quantity' => (float) $stock->quantity,
        'notes' => $stock->notes ?? null,
    ];
}

$productsPayload = [];
foreach ($unsyncedProducts as $prod) {
    $categorySyncUuid = DB::connection('sqlite')->table('categories')->where('id', $prod->category_id)->value('sync_uuid');
    $productsPayload[] = [
        'id' => $prod->id,
        'sync_uuid' => $prod->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
        'category_id' => $prod->category_id,
        'category_sync_uuid' => $categorySyncUuid,
        'name' => $prod->name,
        'slug' => $prod->slug ?? \Illuminate\Support\Str::slug($prod->name),
        'price' => (float) $prod->price,
        'stock_quantity' => (float) ($prod->stock_quantity ?? 0),
        'track_stock' => (bool) ($prod->track_stock ?? false),
        'is_active' => (bool) ($prod->is_active ?? true),
    ];
}

$payload = [
    'batch_id' => 'DEBUG-PUSH-' . time(),
    'stock_movements' => $stockPayload,
    'products' => $productsPayload,
];

echo "Sending PUSH Payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, $payload);

echo "HTTP Status: " . $r->status() . "\n";
echo "Response Body: " . $r->body() . "\n";
