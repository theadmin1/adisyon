<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';

$unsyncedProds = DB::connection('sqlite')->table('products')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();

echo "Unsynced products count: " . $unsyncedProds->count() . "\n";

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
    'batch_id' => 'PUSH-ALL-PRODS-' . time(),
    'products' => $productsPayload,
];

$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, $payload);

echo "HTTP Status: " . $r->status() . "\n";
echo "Response Body: " . $r->body() . "\n";

if ($r->successful()) {
    $syncedUuids = $r->json('synced_uuids') ?? [];
    if (!empty($syncedUuids)) {
        DB::connection('sqlite')->table('products')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
        echo "Updated is_synced = true in SQLite for " . count($syncedUuids) . " products.\n";
    }
}
