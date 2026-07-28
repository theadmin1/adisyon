<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Category;
use App\Models\Product;

echo "=== INSPECTING TABLE SHOW DATA ===\n\n";

$categories = Category::query()
    ->with(['products' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
    ->whereHas('products', fn ($query) => $query->where('is_active', true))
    ->orderBy('sort_order')
    ->get();

echo "Categories count with active products: " . $categories->count() . "\n";
foreach ($categories as $c) {
    echo "  Category: {$c->name} (ID: {$c->id}, Sort: {$c->sort_order})\n";
    echo "    Products count: " . $c->products->count() . "\n";
    foreach ($c->products as $p) {
        echo "      - Product: {$p->name} | Price: {$p->price} | Active: " . ($p->is_active ? '1' : '0') . "\n";
    }
}

echo "\nAll Categories Count: " . Category::count() . "\n";
echo "All Products Count: " . Product::count() . "\n";
echo "Active Products Count: " . Product::where('is_active', true)->count() . "\n";
