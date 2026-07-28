<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

echo "=== FIXING LOCAL PRODUCT CATEGORY IDs ===\n\n";

$catPizza = Category::where('name', 'Pizzalar')->first();
$catBurger = Category::where('name', 'Burgerler')->first();
$catDrink = Category::where('name', 'İçecekler')->first();
$catDessert = Category::where('name', 'Tatlılar')->first();

if ($catPizza) {
    DB::connection('sqlite')->table('products')->whereIn('name', ['Margherita Pizza', 'Pepperoni Pizza', 'Quattro Formaggi', 'Karışık Özel Pizza'])->update(['category_id' => $catPizza->id]);
}
if ($catBurger) {
    DB::connection('sqlite')->table('products')->whereIn('name', ['Cheeseburger', 'Double Smoked Burger', 'Crispy Chicken Burger', 'Truffle Mushroom Burger'])->update(['category_id' => $catBurger->id]);
}
if ($catDrink) {
    DB::connection('sqlite')->table('products')->whereIn('name', ['Coca-Cola 330ml', 'Fanta 330ml', 'Ev Yapımı Limonata', 'Taze Sıkma Portakal Suyu', 'Türk Kahvesi'])->update(['category_id' => $catDrink->id]);
}
if ($catDessert) {
    DB::connection('sqlite')->table('products')->whereIn('name', ['San Sebastian Cheesecake', 'Sıcak Çikolatalı Souffle', 'Fıstıklı Baklava (3 Dilim)', '123'])->update(['category_id' => $catDessert->id]);
}

echo "✅ Product category IDs fixed in SQLite.\n";
