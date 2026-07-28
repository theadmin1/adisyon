<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$p = DB::connection('sqlite')->table('products')->where('id', 9)->first();
echo "SQLite Product 9: Name={$p->name}, stock_quantity={$p->stock_quantity}, is_synced=" . var_export($p->is_synced, true) . "\n";

$sm = DB::connection('sqlite')->table('stock_movements')->where('product_id', 9)->latest()->first();
if ($sm) {
    echo "Latest Stock Movement Product 9: sync_uuid={$sm->sync_uuid}, type={$sm->type}, qty={$sm->quantity}, is_synced=" . var_export($sm->is_synced, true) . "\n";
} else {
    echo "No stock movement found for Product 9!\n";
}
