<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

echo "=== LOCAL SQLITE PRODUCTS ===\n";
$localProds = DB::connection('sqlite')->table('products')->where('name', 'like', '%Kahve%')->get();
foreach ($localProds as $p) {
    echo "ID: {$p->id} | Name: {$p->name} | SKU: " . ($p->sku ?? 'null') . " | SyncUUID: {$p->sync_uuid} | Stock: {$p->stock_quantity} | is_synced: " . var_export($p->is_synced, true) . "\n";
}

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

echo "\n=== REMOTE MYSQL PRODUCTS ===\n";
if ($r->successful()) {
    $remoteProducts = collect($r->json('data.products') ?? []);
    foreach ($remoteProducts as $p) {
        if (str_contains($p['name'], 'Kahve')) {
            echo "ID: {$p['id']} | Name: {$p['name']} | SKU: " . ($p['sku'] ?? 'null') . " | SyncUUID: {$p['sync_uuid']} | Stock: {$p['stock_quantity']}\n";
        }
    }
}
