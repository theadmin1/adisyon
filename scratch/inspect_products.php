<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "=== SQLITE PRODUCTS ===\n";
$sqliteProducts = Product::all();
foreach ($sqliteProducts as $sp) {
    echo "ID: {$sp->id} | Name: {$sp->name} | SyncUUID: {$sp->sync_uuid} | Stock: {$sp->stock_quantity} | TrackStock: " . ($sp->track_stock ? 'true' : 'false') . "\n";
}

echo "\n=== LIVE MYSQL PRODUCTS ===\n";
try {
    $r = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->get($pullUrl);

    if ($r->successful()) {
        $onlineProducts = $r->json('data.products') ?? [];
        foreach ($onlineProducts as $op) {
            echo "ID: {$op['id']} | Name: {$op['name']} | SyncUUID: {$op['sync_uuid']} | Stock: {$op['stock_quantity']} | TrackStock: " . ($op['track_stock'] ? 'true' : 'false') . "\n";
        }
    } else {
        echo "HTTP " . $r->status() . ": " . $r->body() . "\n";
    }
} catch (\Throwable $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
