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

echo "=== FIXING REMOTE PRODUCT SYNC_UUIDs ===\n\n";

$products = Product::all();
$productsPayload = [];

foreach ($products as $prod) {
    $productsPayload[] = [
        'id' => $prod->id,
        'sync_uuid' => $prod->sync_uuid,
        'category_id' => $prod->category_id,
        'name' => $prod->name,
        'slug' => $prod->slug,
        'sku' => $prod->sku,
        'price' => (float) $prod->price,
        'discounted_price' => $prod->discounted_price ? (float) $prod->discounted_price : null,
        'stock_quantity' => (float) $prod->stock_quantity,
        'min_stock_level' => (float) $prod->min_stock_level,
        'unit' => $prod->unit ?? 'adet',
        'track_stock' => (bool) $prod->track_stock,
        'description' => $prod->description,
        'kitchen_department' => $prod->kitchen_department,
        'is_active' => (bool) $prod->is_active,
    ];
}

echo "1. Pushing " . count($productsPayload) . " products with valid sync_uuids to remote server...\n";

$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, [
    'batch_id' => 'BATCH-UUID-FIX-' . time(),
    'products' => $productsPayload,
]);

echo "HTTP Status: " . $r->status() . "\n";
echo "Response: " . $r->body() . "\n\n";

echo "2. Re-checking remote products...\n";
$r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

if ($r2->successful()) {
    $onlineProducts = $r2->json('data.products') ?? [];
    foreach ($onlineProducts as $op) {
        echo "ID: {$op['id']} | Name: {$op['name']} | SyncUUID: '{$op['sync_uuid']}' | Stock: {$op['stock_quantity']}\n";
    }
}
