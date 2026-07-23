<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TableDemoSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::first();

        // 1. Create Halls
        $hallMain = Hall::firstOrCreate(['name' => 'İç Salon'], [
            'branch_id' => $branch?->id,
            'code' => 'SALON',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $hallTerrace = Hall::firstOrCreate(['name' => 'Teras'], [
            'branch_id' => $branch?->id,
            'code' => 'TERAS',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $hallGarden = Hall::firstOrCreate(['name' => 'Bahçe'], [
            'branch_id' => $branch?->id,
            'code' => 'BAHCE',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        // 2. Create Tables
        $halls = [$hallMain, $hallTerrace, $hallGarden];
        
        if (DiningTable::count() === 0) {
            foreach (range(1, 12) as $index) {
                $hall = $halls[($index - 1) % 3];
                DiningTable::create([
                    'branch_id' => $branch?->id,
                    'hall_id' => $hall->id,
                    'name' => 'Masa ' . $index,
                    'code' => 'M' . $index,
                    'capacity' => ($index % 2 === 0) ? 4 : 6,
                    'status' => 'available',
                    'is_active' => true,
                ]);
            }
        }

        // 3. Create Categories & Products
        $categoriesData = [
            'Pizzalar' => [
                ['name' => 'Margherita Pizza', 'price' => 220.00],
                ['name' => 'Pepperoni Pizza', 'price' => 270.00],
                ['name' => 'Quattro Formaggi', 'price' => 290.00],
                ['name' => 'Karışık Özel Pizza', 'price' => 310.00],
            ],
            'Burgerler' => [
                ['name' => 'Cheeseburger', 'price' => 240.00],
                ['name' => 'Double Smoked Burger', 'price' => 320.00],
                ['name' => 'Crispy Chicken Burger', 'price' => 210.00],
                ['name' => 'Truffle Mushroom Burger', 'price' => 340.00],
            ],
            'İçecekler' => [
                ['name' => 'Coca-Cola 330ml', 'price' => 45.00],
                ['name' => 'Fanta 330ml', 'price' => 45.00],
                ['name' => 'Ev Yapımı Limonata', 'price' => 65.00],
                ['name' => 'Taze Sıkma Portakal Suyu', 'price' => 75.00],
                ['name' => 'Türk Kahvesi', 'price' => 50.00],
            ],
            'Tatlılar' => [
                ['name' => 'San Sebastian Cheesecake', 'price' => 140.00],
                ['name' => 'Sıcak Çikolatalı Souffle', 'price' => 130.00],
                ['name' => 'Fıstıklı Baklava (3 Dilim)', 'price' => 160.00],
            ],
        ];

        $sortOrder = 1;
        foreach ($categoriesData as $catName => $products) {
            $category = Category::firstOrCreate(['name' => $catName], [
                'branch_id' => $branch?->id,
                'slug' => Str::slug($catName),
                'sort_order' => $sortOrder++,
                'is_active' => true,
            ]);

            foreach ($products as $prodData) {
                Product::firstOrCreate(['name' => $prodData['name'], 'category_id' => $category->id], [
                    'branch_id' => $branch?->id,
                    'slug' => Str::slug($prodData['name']),
                    'price' => $prodData['price'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
