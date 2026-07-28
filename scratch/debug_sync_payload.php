<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$sm = DB::connection('sqlite')->table('stock_movements')->where('is_synced', 0)->orWhere('is_synced', false)->get();
echo "Unsynced Stock Movements Count: " . count($sm) . "\n";
foreach ($sm as $s) {
    echo "ID: {$s->id} | ProductID: {$s->product_id} | Type: {$s->type} | Qty: {$s->quantity} | SyncUUID: {$s->sync_uuid} | is_synced: {$s->is_synced}\n";
}

$prods = DB::connection('sqlite')->table('products')->where('is_synced', 0)->orWhere('is_synced', false)->get();
echo "\nUnsynced Products Count: " . count($prods) . "\n";
foreach ($prods as $p) {
    echo "ID: {$p->id} | Name: {$p->name} | Stock: {$p->stock_quantity} | SyncUUID: {$p->sync_uuid} | is_synced: {$p->is_synced}\n";
}
