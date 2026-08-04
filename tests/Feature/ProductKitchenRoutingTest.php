<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Checks\CheckService;
use App\Services\KitchenDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProductKitchenRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_products_enabled_for_kitchen_are_dispatched(): void
    {
        Queue::fake();
        $branch = Branch::create(['name' => 'Mutfak Testi', 'code' => 'KIT-01']);
        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => 'Ürünler',
            'slug' => 'urunler',
            'is_active' => true,
        ]);
        $table = DiningTable::create([
            'branch_id' => $branch->id,
            'name' => 'Masa 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);
        $kitchenProduct = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Izgara',
            'sku' => 'KIT-YES',
            'price' => 100,
            'send_to_kitchen' => true,
            'is_active' => true,
        ]);
        $counterProduct = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Şişe Su',
            'sku' => 'KIT-NO',
            'price' => 20,
            'send_to_kitchen' => false,
            'is_active' => true,
        ]);

        $checkService = app(CheckService::class);
        $check = $checkService->openCheck($table);
        $checkService->addItems($check, [
            ['product_id' => $kitchenProduct->id, 'quantity' => 1],
            ['product_id' => $counterProduct->id, 'quantity' => 1],
        ]);
        Setting::set('auto_print_kitchen', '0', 'printing', $branch->id);

        $result = app(KitchenDispatchService::class)->send($check);

        $this->assertSame(1, $result['sent_count']);
        $this->assertDatabaseHas('check_items', [
            'check_id' => $check->id,
            'product_id' => $kitchenProduct->id,
            'kitchen_status' => 'received',
        ]);
        $this->assertDatabaseHas('check_items', [
            'check_id' => $check->id,
            'product_id' => $counterProduct->id,
            'kitchen_status' => 'not_required',
            'sent_to_kitchen_at' => null,
        ]);
    }

    public function test_relative_product_images_are_presented_as_absolute_urls(): void
    {
        $branch = Branch::create(['name' => 'Görsel Testi', 'code' => 'IMG-01']);
        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => 'Ürünler',
            'slug' => 'urunler',
            'is_active' => true,
        ]);
        $product = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Görselli Ürün',
            'sku' => 'IMG-1',
            'price' => 10,
            'image_path' => 'uploads/products/product.webp',
        ]);

        $this->assertSame(asset('uploads/products/product.webp'), $product->image_url);

        $product->image_path = 'data:image/webp;base64,AAAA';
        $this->assertSame($product->image_path, $product->image_url);
    }

    public function test_quick_sale_respects_each_products_kitchen_setting(): void
    {
        Queue::fake();
        $branch = Branch::create(['name' => 'Hızlı Satış', 'code' => 'QCK-01']);
        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => 'Ürünler',
            'slug' => 'urunler',
            'is_active' => true,
        ]);
        $user = User::create([
            'branch_id' => $branch->id,
            'name' => 'Yönetici',
            'email' => 'quick@example.test',
            'password' => 'secret-password',
        ]);
        $staff = StaffProfile::create([
            'branch_id' => $branch->id,
            'name' => 'Kasiyer',
            'role' => 'Yönetici',
            'pin_code' => 'migrated',
            'pin_hash' => bcrypt('1234'),
            'pin_length' => 4,
            'is_active' => true,
        ]);
        $kitchenProduct = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Tost',
            'sku' => 'QCK-YES',
            'price' => 100,
            'send_to_kitchen' => true,
            'is_active' => true,
        ]);
        $counterProduct = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Su',
            'sku' => 'QCK-NO',
            'price' => 20,
            'send_to_kitchen' => false,
            'is_active' => true,
        ]);
        Setting::set('auto_print_kitchen', '0', 'printing', $branch->id);

        $this->actingAs($user)->withSession([
            'active_staff_id' => $staff->id,
            'active_staff_name' => $staff->name,
            'active_staff_role' => $staff->role,
        ])->postJson(route('quicksale.store'), [
            'items' => [
                ['product_id' => $kitchenProduct->id, 'quantity' => 1],
                ['product_id' => $counterProduct->id, 'quantity' => 1],
            ],
            'payment_method' => 'nakit',
            'send_to_kitchen' => true,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('check_items', [
            'product_id' => $kitchenProduct->id,
            'kitchen_status' => 'received',
        ]);
        $this->assertDatabaseHas('check_items', [
            'product_id' => $counterProduct->id,
            'kitchen_status' => 'not_required',
            'sent_to_kitchen_at' => null,
        ]);
    }
}
