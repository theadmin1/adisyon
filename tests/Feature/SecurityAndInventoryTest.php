<?php

namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Check;
use App\Models\DeliveryIntegration;
use App\Models\DeliveryOrder;
use App\Models\Device;
use App\Models\License;
use App\Models\Product;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Checks\CheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecurityAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_license_is_rejected_without_creating_records(): void
    {
        $this->postJson('/api/v1/license/verify', [
            'license_key' => 'missing-license',
            'device_guid' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->assertDatabaseCount('licenses', 0);
        $this->assertDatabaseCount('devices', 0);
    }

    public function test_device_receives_key_once_and_only_its_hash_is_stored(): void
    {
        $branch = $this->branch('A');
        $license = License::create([
            'branch_id' => $branch->id,
            'license_key' => 'LICENSE-A',
            'status' => 'Active',
            'expires_at' => now()->addMonth(),
            'max_devices' => 1,
        ]);
        $guid = (string) Str::uuid();

        $response = $this->postJson('/api/v1/license/verify', [
            'license_key' => $license->license_key,
            'device_guid' => $guid,
            'device_code' => 'KASA-01',
        ])->assertOk();

        $apiKey = $response->json('api_key');
        $device = Device::where('device_guid', $guid)->firstOrFail();

        $this->assertNull($device->getRawOriginal('api_key'));
        $this->assertSame(hash('sha256', $apiKey), $device->api_key_hash);

        $this->withHeader('X-Device-Api-Key', $apiKey)
            ->postJson('/api/v1/device/ping', ['device_guid' => $guid])
            ->assertOk();
    }

    public function test_branch_scope_hides_other_tenant_products(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');
        $user = $this->userFor($branchA);

        $this->productFor($branchA, 'A ürünü', 10);
        $this->productFor($branchB, 'B ürünü', 10);

        $this->actingAs($user);

        $this->assertSame(['A ürünü'], Product::pluck('name')->all());
    }

    public function test_sale_deducts_fractional_stock_and_removal_restores_it_once(): void
    {
        $branch = $this->branch('A');
        $user = $this->userFor($branch);
        $product = $this->productFor($branch, 'Kahve', 5);
        $check = Check::create([
            'branch_id' => $branch->id,
            'waiter_id' => $user->id,
            'check_number' => 'CHK-TEST',
            'sync_uuid' => (string) Str::uuid(),
            'status' => CheckStatus::Open,
            'opened_at' => now(),
        ]);
        $service = app(CheckService::class);

        $this->actingAs($user);
        $service->addItems($check, [['product_id' => $product->id, 'quantity' => 1.5]]);

        $this->assertSame('3.50', $product->fresh()->stock_quantity);

        $item = $check->items()->firstOrFail();
        $service->removeItem($item);

        $this->assertSame('5.00', $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $branch->id,
            'type' => 'return_approved',
        ]);
    }

    public function test_update_download_requires_a_valid_device_key(): void
    {
        $this->getJson('/api/v1/update/download-package')->assertUnauthorized();
    }

    public function test_insufficient_stock_rolls_back_the_sale(): void
    {
        $branch = $this->branch('A');
        $user = $this->userFor($branch);
        $product = $this->productFor($branch, 'Çay', 1);
        $check = Check::create([
            'branch_id' => $branch->id,
            'waiter_id' => $user->id,
            'check_number' => 'CHK-NO-STOCK',
            'sync_uuid' => (string) Str::uuid(),
            'status' => CheckStatus::Open,
            'opened_at' => now(),
        ]);

        $this->actingAs($user);

        try {
            app(CheckService::class)->addItems($check, [
                ['product_id' => $product->id, 'quantity' => 2],
            ]);
            $this->fail('Yetersiz stok satışı kabul edildi.');
        } catch (ValidationException|\RuntimeException) {
            $this->assertSame('1.00', $product->fresh()->stock_quantity);
            $this->assertDatabaseCount('check_items', 0);
        }
    }

    public function test_unsigned_delivery_webhook_is_rejected(): void
    {
        $branch = $this->branch('A');
        DeliveryIntegration::create([
            'branch_id' => $branch->id,
            'channel' => 'trendyol',
            'store_id' => 'STORE-A',
            'is_active' => true,
        ]);
        config()->set('services.delivery.webhook_secrets.trendyol', 'webhook-secret');

        $this->postJson('/api/v1/integrations/trendyol-go/webhook', [
            'storeId' => 'STORE-A',
            'orderId' => 'ORDER-1',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('delivery_orders', 0);
    }

    public function test_central_admin_cannot_enter_unscoped_restaurant_portal(): void
    {
        $admin = User::create([
            'name' => 'Central Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_product_cannot_be_attached_to_another_branch_category(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');
        $user = $this->userFor($branchA);
        $foreignCategory = Category::create([
            'branch_id' => $branchB->id,
            'name' => 'Yabancı kategori',
            'slug' => 'yabanci-kategori',
            'is_active' => true,
        ]);

        $this->actingAsRestaurantStaff($user, $branchA)
            ->post('/products', [
                'category_id' => $foreignCategory->id,
                'name' => 'Yanlış ürün',
                'price' => 10,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('products', ['name' => 'Yanlış ürün']);
    }

    public function test_phone_order_uses_server_product_name_and_price(): void
    {
        $branch = $this->branch('A');
        $user = $this->userFor($branch);
        $product = $this->productFor($branch, 'Gerçek ürün', 10);
        $product->update(['price' => 12, 'discounted_price' => 7.5]);

        $this->actingAsRestaurantStaff($user, $branch)
            ->postJson('/delivery/phone-order', [
                'customer_name' => 'Müşteri',
                'customer_phone' => '5551112233',
                'delivery_address' => 'Test adresi',
                'payment_method' => 'cash_on_delivery',
                'items' => [[
                    'product_id' => $product->id,
                    'name' => 'Sahte ad',
                    'price' => 0.01,
                    'quantity' => 2,
                ]],
            ])
            ->assertOk();

        $order = DeliveryOrder::firstOrFail();
        $this->assertSame('15.00', $order->subtotal);
        $this->assertSame('Gerçek ürün', $order->items[0]['name']);
        $this->assertSame(7.5, $order->items[0]['price']);
    }

    public function test_integration_credentials_are_not_rendered_back_to_the_browser(): void
    {
        $branch = $this->branch('A');
        $user = $this->userFor($branch);
        DeliveryIntegration::create([
            'branch_id' => $branch->id,
            'channel' => 'trendyol',
            'api_key' => 'private-api-key',
            'api_secret' => 'private-api-secret',
            'is_active' => true,
        ]);

        $this->actingAsRestaurantStaff($user, $branch)
            ->get('/settings?tab=integrations')
            ->assertOk()
            ->assertDontSee('private-api-key')
            ->assertDontSee('private-api-secret');
    }

    private function branch(string $suffix): Branch
    {
        return Branch::create([
            'name' => "Şube {$suffix}",
            'code' => "SUBE-{$suffix}",
            'is_active' => true,
        ]);
    }

    private function userFor(Branch $branch): User
    {
        return User::create([
            'branch_id' => $branch->id,
            'name' => 'Kasiyer',
            'email' => strtolower($branch->code).'@example.test',
            'password' => 'secret-password',
            'is_admin' => false,
        ]);
    }

    private function actingAsRestaurantStaff(User $user, Branch $branch): static
    {
        $staff = StaffProfile::create([
            'branch_id' => $branch->id,
            'name' => 'Yönetici',
            'role' => 'Yönetici',
            'pin_code' => 'migrated',
            'pin_hash' => bcrypt('1234'),
            'pin_length' => 4,
            'avatar_color' => 'indigo',
            'is_active' => true,
        ]);

        return $this->actingAs($user)->withSession([
            'active_staff_id' => $staff->id,
            'active_staff_name' => $staff->name,
            'active_staff_role' => $staff->role,
            'active_staff_color' => $staff->avatar_color,
        ]);
    }

    private function productFor(Branch $branch, string $name, float $stock): Product
    {
        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => "{$name} kategorisi",
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);

        return Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'price' => 10,
            'stock_quantity' => $stock,
            'min_stock_level' => 0,
            'unit' => 'adet',
            'track_stock' => true,
            'is_active' => true,
        ]);
    }
}
