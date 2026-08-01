<?php

namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\TableStatus;
use App\Events\WaiterRealtimeUpdated;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Product;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaiterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_can_login_with_restaurant_credentials_and_pin(): void
    {
        [$branch, $user, $staff] = $this->identity('AUTH');

        $this->getJson('/api/v1/waiter/halls')->assertUnauthorized();

        $this->postJson('/api/v1/waiter/auth/profiles', [
            'restaurant_id' => $user->restaurant_id,
            'password' => 'restaurant-secret',
        ])->assertOk()
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.profiles.0.id', $staff->id);

        $login = $this->postJson('/api/v1/waiter/auth/login', [
            'restaurant_id' => $user->restaurant_id,
            'password' => 'restaurant-secret',
            'profile_id' => $staff->id,
            'pin' => '1234',
            'device_name' => 'Flutter Test',
        ])->assertOk()
            ->assertJsonPath('data.staff.id', $staff->id)
            ->assertJsonStructure(['data' => ['access_token', 'expires_at']]);

        $token = $login->json('data.access_token');
        $this->withToken($token)
            ->getJson('/api/v1/waiter/auth/me')
            ->assertOk()
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.staff.name', $staff->name);

        $this->postJson('/api/v1/waiter/auth/login', [
            'restaurant_id' => $user->restaurant_id,
            'password' => 'restaurant-secret',
            'profile_id' => $staff->id,
            'pin' => '9999',
        ])->assertUnprocessable();

        $this->withToken($token)
            ->postJson('/api/v1/waiter/auth/logout')
            ->assertOk();
        $this->withToken($token)
            ->getJson('/api/v1/waiter/auth/me')
            ->assertUnauthorized();
    }

    public function test_tables_and_products_are_scoped_to_authenticated_branch(): void
    {
        [$branchA, $userA, $staffA] = $this->identity('SCOPE-A');
        [$branchB] = $this->identity('SCOPE-B');
        [$hallA, $tableA] = $this->table($branchA, 'A Masası');
        [, $tableB] = $this->table($branchB, 'B Masası');
        $productA = $this->product($branchA, 'A Ürünü');
        $productB = $this->product($branchB, 'B Ürünü');
        $token = $this->token($userA, $staffA);

        $this->withToken($token)
            ->getJson('/api/v1/waiter/halls')
            ->assertOk()
            ->assertJsonPath('data.0.id', $hallA->id)
            ->assertJsonFragment(['id' => $tableA->id, 'name' => $tableA->name])
            ->assertJsonMissing(['id' => $tableB->id, 'name' => $tableB->name]);

        $this->withToken($token)
            ->getJson('/api/v1/waiter/products')
            ->assertOk()
            ->assertJsonFragment(['id' => $productA->id, 'name' => $productA->name])
            ->assertJsonMissing(['id' => $productB->id, 'name' => $productB->name]);

        $this->withToken($token)
            ->getJson("/api/v1/waiter/tables/{$tableB->id}")
            ->assertNotFound();
    }

    public function test_realtime_channel_is_private_to_branch_and_model_changes_are_broadcast(): void
    {
        [$branch, $user, $staff] = $this->identity('REALTIME');
        [$foreignBranch] = $this->identity('REALTIME-FOREIGN');
        $token = $this->token($user, $staff);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'broadcasting.connections.reverb.options.host' => 'localhost',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);
        $this->withToken($token)
            ->getJson('/api/v1/waiter/realtime/config')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.channel', "private-waiter.branch.{$branch->id}")
            ->assertJsonPath('data.event', 'waiter.updated');

        $this->withToken($token)
            ->postJson('/api/v1/waiter/realtime/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-waiter.branch.{$branch->id}",
            ])->assertOk()
            ->assertJsonStructure(['auth']);

        $this->withToken($token)
            ->postJson('/api/v1/waiter/realtime/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-waiter.branch.{$foreignBranch->id}",
            ])->assertForbidden();

        Event::fake([WaiterRealtimeUpdated::class]);
        [, $table] = $this->table($branch, 'Canlı Masa');

        Event::assertDispatched(
            WaiterRealtimeUpdated::class,
            fn (WaiterRealtimeUpdated $event): bool => $event->branchId === $branch->id
                && in_array('tables', $event->topics, true)
                && $event->references['table_id'] === $table->id,
        );
    }

    public function test_order_kitchen_and_payment_workflow_is_idempotent(): void
    {
        [$branch, $user, $staff] = $this->identity('FLOW');
        [, $table] = $this->table($branch, 'Bahçe 1');
        $product = $this->product($branch, 'Köfte', 10, 125);
        $token = $this->token($user, $staff);
        $orderReference = (string) Str::uuid();

        $payload = [
            'client_reference' => $orderReference,
            'dining_table_id' => $table->id,
            'guest_count' => 2,
            'customer_notes' => 'Fıstık alerjisi var.',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'notes' => 'İyi pişmiş',
            ]],
        ];
        $created = $this->withToken($token)
            ->postJson('/api/v1/waiter/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.total', 250)
            ->assertJsonPath('data.items.0.added_by', $staff->name);
        $orderId = $created->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/waiter/orders', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->withToken($token)
            ->postJson("/api/v1/waiter/orders/{$orderId}/send-kitchen")
            ->assertOk()
            ->assertJsonPath('data.sent_count', 1)
            ->assertJsonPath('data.order.items.0.kitchen_status', 'received');

        $itemId = $created->json('data.items.0.id');
        $this->assertDatabaseHas('check_items', ['id' => $itemId, 'kitchen_status' => 'received']);
        CheckItem::whereKey($itemId)->update(['kitchen_status' => 'delivered']);

        $this->withToken($token)
            ->getJson('/api/v1/waiter/kitchen/updates?mine=1')
            ->assertOk()
            ->assertJsonPath('data.0.order.id', $orderId);

        $this->withToken($token)
            ->postJson("/api/v1/waiter/kitchen/items/{$itemId}/served")
            ->assertOk()
            ->assertJsonPath('data.kitchen_status', 'served');

        $this->withToken($token)
            ->postJson("/api/v1/waiter/orders/{$orderId}/request-payment")
            ->assertOk()
            ->assertJsonPath('data.status', CheckStatus::AwaitingPayment->value);

        $paymentReference = (string) Str::uuid();
        $partial = $this->withToken($token)
            ->postJson("/api/v1/waiter/orders/{$orderId}/payments", [
                'client_reference' => $paymentReference,
                'method' => 'nakit',
                'amount' => 100,
            ])->assertCreated()
            ->assertJsonPath('data.order.remaining', 150)
            ->assertJsonPath('data.order.status', CheckStatus::AwaitingPayment->value);
        $paymentId = $partial->json('data.payment.id');

        $this->withToken($token)
            ->postJson("/api/v1/waiter/orders/{$orderId}/payments", [
                'client_reference' => $paymentReference,
                'method' => 'nakit',
                'amount' => 100,
            ])->assertOk()
            ->assertJsonPath('data.payment.id', $paymentId)
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->withToken($token)
            ->postJson("/api/v1/waiter/orders/{$orderId}/payments", [
                'client_reference' => (string) Str::uuid(),
                'method' => 'kredi_karti',
            ])->assertCreated()
            ->assertJsonPath('data.order.remaining', 0)
            ->assertJsonPath('data.order.status', CheckStatus::Closed->value);

        $this->assertSame(TableStatus::Available, $table->fresh()->status);
        $this->assertSame('8.00', $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('payments', 2);
    }

    /** @return array{Branch, User, StaffProfile} */
    private function identity(string $suffix): array
    {
        $branch = Branch::create([
            'name' => "Mobil Şube {$suffix}",
            'code' => "MOB-{$suffix}",
            'is_active' => true,
        ]);
        $user = User::create([
            'branch_id' => $branch->id,
            'restaurant_id' => "REST-{$suffix}",
            'name' => "Restoran {$suffix}",
            'email' => strtolower("mobile-{$suffix}@example.test"),
            'password' => 'restaurant-secret',
            'is_admin' => false,
        ]);
        $staff = StaffProfile::create([
            'branch_id' => $branch->id,
            'name' => "Garson {$suffix}",
            'role' => 'Garson',
            'pin_code' => 'migrated',
            'pin_hash' => bcrypt('1234'),
            'pin_length' => 4,
            'avatar_color' => 'blue',
            'is_active' => true,
        ]);

        return [$branch, $user, $staff];
    }

    /** @return array{Hall, DiningTable} */
    private function table(Branch $branch, string $name): array
    {
        $hall = Hall::create([
            'branch_id' => $branch->id,
            'name' => "Salon {$name}",
            'is_active' => true,
        ]);
        $table = DiningTable::create([
            'branch_id' => $branch->id,
            'hall_id' => $hall->id,
            'name' => $name,
            'status' => TableStatus::Available,
            'is_active' => true,
        ]);

        return [$hall, $table];
    }

    private function product(Branch $branch, string $name, float $stock = 10, float $price = 50): Product
    {
        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => "Kategori {$name}",
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ]);

        return Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'price' => $price,
            'stock_quantity' => $stock,
            'min_stock_level' => 0,
            'unit' => 'adet',
            'track_stock' => true,
            'is_active' => true,
        ]);
    }

    private function token(User $user, StaffProfile $staff): string
    {
        return $this->postJson('/api/v1/waiter/auth/login', [
            'restaurant_id' => $user->restaurant_id,
            'password' => 'restaurant-secret',
            'profile_id' => $staff->id,
            'pin' => '1234',
        ])->assertOk()->json('data.access_token');
    }
}
