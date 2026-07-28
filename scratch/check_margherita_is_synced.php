<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

$prods = DB::connection('sqlite')->table('products')->where('name', 'like', '%Pizza%')->get();
foreach ($prods as $p) {
    echo "ID: {$p->id} | Name: {$p->name} | SyncUUID: {$p->sync_uuid} | Stock: {$p->stock_quantity} | is_synced: " . var_export($p->is_synced, true) . "\n";
}
