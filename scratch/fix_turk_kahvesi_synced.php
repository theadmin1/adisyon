<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$affected = DB::connection('sqlite')->table('products')->where('name', 'like', '%Kahve%')->update(['is_synced' => true]);
echo "Affected rows: {$affected}\n";

$p = DB::connection('sqlite')->table('products')->where('name', 'like', '%Kahve%')->first();
echo "is_synced value: " . var_export($p->is_synced, true) . "\n";
