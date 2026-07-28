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

echo "=== TEST DIRECT PRODUCT PUSH TO REMOTE ===\n\n";

$p = Product::find(1);
echo "Local Product: {$p->name} | SyncUUID: {$p->sync_uuid} | Stock: {$p->stock_quantity}\n";

$payload = [
    'batch_id' => 'TEST-DIRECT-' . time(),
    'products' => [
        [
            'sync_uuid' => $p->sync_uuid,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => (float) $p->price,
            'stock_quantity' => 85.00, // Explicitly set to 85.00 to verify!
            'track_stock' => true,
            'is_active' => true,
        ]
    ]
];

echo "Sending explicit push with stock_quantity = 85.00...\n";
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, $payload);

echo "HTTP Status: " . $r->status() . "\n";
echo "Response: " . $r->body() . "\n\n";

echo "Checking remote server stock via pull...\n";
$r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if ($r2->successful()) {
    $remoteProducts = collect($r2->json('data.products') ?? []);
    $rProd = $remoteProducts->firstWhere('sync_uuid', $p->sync_uuid);
    if ($rProd) {
        echo "✅ Remote Product Found: ID: {$rProd['id']} | Name: {$rProd['name']} | Stock: {$rProd['stock_quantity']}\n";
    } else {
        echo "❌ Remote product not found!\n";
    }
}
