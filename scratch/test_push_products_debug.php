<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';

$unsyncedProducts = DB::connection('sqlite')->table('products')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();

echo "Unsynced products in SQLite: " . $unsyncedProducts->count() . "\n";
foreach ($unsyncedProducts as $p) {
    echo "  ID: {$p->id} | Name: {$p->name} | SyncUUID: {$p->sync_uuid} | Stock: {$p->stock_quantity}\n";
}

$productsPayload = [];
foreach ($unsyncedProducts as $prod) {
    $categorySyncUuid = DB::connection('sqlite')->table('categories')->where('id', $prod->category_id)->value('sync_uuid');
    $productsPayload[] = [
        'sync_uuid' => $prod->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
        'category_id' => $prod->category_id,
        'category_sync_uuid' => $categorySyncUuid,
        'name' => $prod->name,
        'slug' => $prod->slug ?? \Illuminate\Support\Str::slug($prod->name),
        'sku' => $prod->sku ?? null,
        'price' => (float) $prod->price,
        'discounted_price' => $prod->discounted_price ? (float) $prod->discounted_price : null,
        'stock_quantity' => (float) ($prod->stock_quantity ?? 0),
        'min_stock_level' => (float) ($prod->min_stock_level ?? 0),
        'unit' => $prod->unit ?? 'adet',
        'track_stock' => (bool) ($prod->track_stock ?? false),
        'description' => $prod->description ?? null,
        'kitchen_department' => $prod->kitchen_department ?? null,
        'is_active' => (bool) ($prod->is_active ?? true),
    ];
}

$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, [
    'batch_id' => 'DEBUG-PROD-PUSH-' . time(),
    'products' => $productsPayload,
]);

echo "HTTP Status: " . $r->status() . "\n";
echo "Response Body: " . $r->body() . "\n";
