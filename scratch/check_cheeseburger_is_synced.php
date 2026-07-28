<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

$cb = DB::connection('sqlite')->table('products')->where('name', 'like', '%Cheeseburger%')->first();
echo "Cheeseburger SQLite record:\n";
echo "ID: {$cb->id} | Name: {$cb->name} | SyncUUID: {$cb->sync_uuid} | Stock: {$cb->stock_quantity} | is_synced: " . var_export($cb->is_synced, true) . "\n\n";

$unsyncedProds = DB::connection('sqlite')->table('products')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
echo "All unsynced products count: " . $unsyncedProds->count() . "\n";
foreach ($unsyncedProds as $p) {
    echo " - ID: {$p->id} | Name: {$p->name} | Stock: {$p->stock_quantity} | is_synced: " . var_export($p->is_synced, true) . "\n";
}
