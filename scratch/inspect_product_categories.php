<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Category;
use App\Models\Product;

echo "=== CATEGORIES IN SQLITE ===\n";
foreach (Category::all() as $c) {
    echo "  Category ID: {$c->id} | Name: {$c->name} | SyncUUID: {$c->sync_uuid}\n";
}

echo "\n=== PRODUCTS IN SQLITE ===\n";
foreach (Product::all() as $p) {
    echo "  Product ID: {$p->id} | Name: {$p->name} | category_id: " . var_export($p->category_id, true) . " | SyncUUID: {$p->sync_uuid}\n";
}
