<?php

namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Enums\TableStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Check;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Product;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaiterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_can_see_open_checks_and_filter_own_tables(): void
    {
        [$branch, $user, $staff] = $this->identity('A');
        $own = $this->check($branch, $user, $staff, 'Masa 1');
        $other = $this->check($branch, $user, null, 'Masa 2');

        $this->actingAsStaff($user, $staff)
            ->get(route('waiter.index'))
            ->assertOk()
            ->assertSee('Masa 1')
            ->assertSee('Masa 2');

        $this->actingAsStaff($user, $staff)
            ->get(route('waiter.index', ['scope' => 'mine']))
            ->assertOk()
            ->assertSee($own->check_number)
            ->assertDontSee($other->check_number);
    }

    public function test_waiter_adds_product_with_note_and_is_attributed(): void
    {
        [$branch, $user, $staff] = $this->identity('B');
        $check = $this->check($branch, $user, null, 'Bahçe 3', CheckStatus::AwaitingPayment);
        $product = $this->product($branch, 'Az pişmiş burger', 10);

        $this->actingAsStaff($user, $staff)
            ->post(route('waiter.checks.items.store', $check), [
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'notes' => 'Soğansız, birisi iyi pişmiş',
                ]],
            ])
            ->assertRedirect(route('waiter.checks.show', ['check' => $check, 'scope' => 'all']));

        $item = $check->items()->firstOrFail();
        $this->assertSame($staff->id, $item->added_by_staff_profile_id);
        $this->assertSame($staff->name, $item->added_by_name);
        $this->assertSame('Soğansız, birisi iyi pişmiş', $item->notes);
        $this->assertSame('8.00', $product->fresh()->stock_quantity);
        $this->assertSame(CheckStatus::Open, $check->fresh()->status);
        $this->assertSame(TableStatus::Occupied, $check->diningTable->fresh()->status);
        $this->assertSame($staff->id, $check->fresh()->waiter_staff_profile_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'waiter.check_items_added']);
    }

    public function test_waiter_cannot_add_another_branch_product(): void
    {
        [$branchA, $userA, $staffA] = $this->identity('C');
        [$branchB] = $this->identity('D');
        $check = $this->check($branchA, $userA, $staffA, 'Masa 4');
        $foreignProduct = $this->product($branchB, 'Başka şube ürünü', 10);

        $this->actingAsStaff($userA, $staffA)
            ->post(route('waiter.checks.items.store', $check), [
                'items' => [['product_id' => $foreignProduct->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('check_items', 0);
    }

    public function test_waiter_can_save_customer_note_and_request_payment(): void
    {
        [$branch, $user, $staff] = $this->identity('E');
        $check = $this->check($branch, $user, $staff, 'Teras 2');
        $product = $this->product($branch, 'Çay', 20);
        $check->items()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sync_uuid' => (string) Str::uuid(),
            'unit_price' => 20,
            'quantity' => 1,
            'total_price' => 20,
        ]);

        $this->actingAsStaff($user, $staff)
            ->put(route('waiter.checks.notes.update', $check), [
                'customer_notes' => 'Fıstık alerjisi var.',
            ])
            ->assertRedirect();
        $this->assertSame('Fıstık alerjisi var.', $check->fresh()->customer_notes);

        $this->actingAsStaff($user, $staff)
            ->post(route('waiter.checks.request-payment', $check))
            ->assertRedirect();

        $this->assertSame(CheckStatus::AwaitingPayment, $check->fresh()->status);
        $this->assertSame(TableStatus::AwaitingPayment, $check->diningTable->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'waiter.customer_notes_updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'waiter.payment_requested']);
    }

    public function test_product_added_after_kitchen_send_creates_a_new_unsent_line(): void
    {
        [$branch, $user, $staff] = $this->identity('G');
        $check = $this->check($branch, $user, $staff, 'Masa 7');
        $product = $this->product($branch, 'Limonata', 10);
        $check->items()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'added_by_staff_profile_id' => $staff->id,
            'added_by_name' => $staff->name,
            'product_name' => $product->name,
            'sync_uuid' => (string) Str::uuid(),
            'unit_price' => 20,
            'quantity' => 1,
            'total_price' => 20,
            'kitchen_status' => 'received',
            'sent_to_kitchen_at' => now(),
        ]);

        $this->actingAsStaff($user, $staff)
            ->post(route('waiter.checks.items.store', $check), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertRedirect();

        $this->assertSame(2, $check->items()->count());
        $this->assertSame(1, $check->items()->whereNull('sent_to_kitchen_at')->count());
    }

    public function test_closed_check_cannot_be_changed_from_waiter_screen(): void
    {
        [$branch, $user, $staff] = $this->identity('F');
        $check = $this->check($branch, $user, $staff, 'Masa 9', CheckStatus::Closed);
        $product = $this->product($branch, 'Kahve', 10);

        $this->actingAsStaff($user, $staff)
            ->post(route('waiter.checks.items.store', $check), [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(409);

        $this->assertDatabaseCount('check_items', 0);
    }

    /**
     * @return array{Branch, User, StaffProfile}
     */
    private function identity(string $suffix): array
    {
        $branch = Branch::create([
            'name' => "Garson Şubesi {$suffix}",
            'code' => "WAIT-{$suffix}",
            'is_active' => true,
        ]);
        $user = User::create([
            'branch_id' => $branch->id,
            'name' => "Restoran Kullanıcısı {$suffix}",
            'email' => "waiter-{$suffix}@example.test",
            'password' => 'secret-password',
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

    private function actingAsStaff(User $user, StaffProfile $staff): static
    {
        return $this->actingAs($user)->withSession([
            'active_staff_id' => $staff->id,
            'active_staff_name' => $staff->name,
            'active_staff_role' => $staff->role,
            'active_staff_color' => $staff->avatar_color,
        ]);
    }

    private function check(
        Branch $branch,
        User $user,
        ?StaffProfile $staff,
        string $tableName,
        CheckStatus $status = CheckStatus::Open
    ): Check {
        $hall = Hall::create([
            'branch_id' => $branch->id,
            'name' => "Salon {$tableName}",
            'is_active' => true,
        ]);
        $table = DiningTable::create([
            'branch_id' => $branch->id,
            'hall_id' => $hall->id,
            'name' => $tableName,
            'status' => $status === CheckStatus::AwaitingPayment ? TableStatus::AwaitingPayment : TableStatus::Occupied,
            'is_active' => true,
        ]);

        return Check::create([
            'branch_id' => $branch->id,
            'dining_table_id' => $table->id,
            'waiter_id' => $user->id,
            'waiter_staff_profile_id' => $staff?->id,
            'waiter_name' => $staff?->name,
            'check_number' => 'WAIT-'.Str::upper(Str::random(10)),
            'sync_uuid' => (string) Str::uuid(),
            'guest_count' => 2,
            'status' => $status,
            'opened_at' => now(),
        ]);
    }

    private function product(Branch $branch, string $name, float $stock): Product
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
            'price' => 20,
            'stock_quantity' => $stock,
            'min_stock_level' => 0,
            'unit' => 'adet',
            'track_stock' => true,
            'is_active' => true,
        ]);
    }
}
