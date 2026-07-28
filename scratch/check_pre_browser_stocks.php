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

$prod = Product::where('name', 'like', '%Pizza%')->first();
$r = Http::withoutVerifying()->timeout(15)->withHeaders(['X-Device-Api-Key' => $apiKey, 'Accept' => 'application/json'])->get($pullUrl);
$onProd = collect($r->json('data.products') ?? [])->firstWhere('sync_uuid', $prod->sync_uuid);

echo "PRE-BROWSER CHECK:\n";
echo "Product: {$prod->name}\n";
echo "SQLite Local Stock: {$prod->stock_quantity}\n";
echo "MySQL Online Stock: " . ($onProd ? $onProd['stock_quantity'] : 'N/A') . "\n";
