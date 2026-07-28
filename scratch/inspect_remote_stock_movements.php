<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "=== INSPECTING REMOTE STOCK MOVEMENTS & PRODUCTS ===\n\n";

$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if ($r->successful()) {
    $remoteProducts = collect($r->json('data.products') ?? []);
    $remoteMovements = collect($r->json('data.stock_movements') ?? []);

    echo "Total Remote Products: " . $remoteProducts->count() . "\n";
    echo "Total Remote Stock Movements: " . $remoteMovements->count() . "\n\n";

    echo "--- REMOTE PRODUCTS ---\n";
    foreach ($remoteProducts as $p) {
        echo "ID: {$p['id']} | Name: {$p['name']} | SyncUUID: {$p['sync_uuid']} | Stock: {$p['stock_quantity']}\n";
    }

    echo "\n--- REMOTE STOCK MOVEMENTS ---\n";
    foreach ($remoteMovements as $sm) {
        $prodName = $remoteProducts->firstWhere('id', $sm['product_id'])['name'] ?? 'UNKNOWN PROD (ID: '.$sm['product_id'].')';
        echo "ID: {$sm['id']} | ProdName: {$prodName} | Type: {$sm['type']} | Qty: {$sm['quantity']} | SyncUUID: {$sm['sync_uuid']}\n";
    }
}
