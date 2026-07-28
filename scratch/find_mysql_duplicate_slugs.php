<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "=== INSPECTING MYSQL PRODUCTS FOR DUPLICATE SLUGS ===\n\n";

$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if ($r->successful()) {
    $products = collect($r->json('data.products') ?? []);
    echo "Total Remote Products: " . $products->count() . "\n";
    foreach ($products as $p) {
        echo "ID: {$p['id']} | Name: '{$p['name']}' | Slug: '{$p['slug']}' | SyncUUID: '{$p['sync_uuid']}' | Stock: {$p['stock_quantity']}\n";
    }
}
