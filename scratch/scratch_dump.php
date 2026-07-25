<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "CONN: " . config('database.default') . PHP_EOL;
echo "MYSQL HOST: " . config('database.connections.mysql.host') . PHP_EOL;
echo "MYSQL DB: " . config('database.connections.mysql.database') . PHP_EOL;
echo "HALLS: " . \App\Models\Hall::count() . PHP_EOL;
echo "TABLES: " . \App\Models\DiningTable::count() . PHP_EOL;
echo "PRODUCTS: " . \App\Models\Product::count() . PHP_EOL;
echo "CHECKS: " . \App\Models\Check::count() . PHP_EOL;
