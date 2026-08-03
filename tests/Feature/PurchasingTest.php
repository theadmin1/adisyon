<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StaffProfile;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchasing_page_renders_with_product_options(): void
    {
        [$branch, $user, $staff] = $this->identity('PAGE');
        $this->product($branch, 'Domates', 12);
        Supplier::create([
            'branch_id' => $branch->id,
            'name' => 'Sebze Tedarikçisi',
            'is_active' => true,
        ]);

        $this->actingAsStaff($user, $staff)
            ->get(route('purchasing.index'))
            ->assertOk()
            ->assertSee('Domates')
            ->assertSee('Sebze Tedarikçisi');
    }

    public function test_purchasing_page_tolerates_supplier_credentials_encrypted_with_an_old_key(): void
    {
        [$branch, $user, $staff] = $this->identity('LEGACY');
        $supplier = Supplier::create([
            'branch_id' => $branch->id,
            'name' => 'Eski Portal Tedarikçisi',
            'is_active' => true,
            'portal_enabled' => true,
        ]);
        $supplier->getConnection()->table('suppliers')->where('id', $supplier->id)->update([
            'portal_token_hash' => hash('sha256', 'legacy-token'),
            'portal_token' => 'old-key-ciphertext',
            'portal_code_hash' => hash('sha256', '1234'),
            'portal_code' => 'old-key-ciphertext',
        ]);

        $this->actingAsStaff($user, $staff)
            ->get(route('purchasing.index', ['tab' => 'supplier-products']))
            ->assertOk()
            ->assertSee('Eski Portal Tedarikçisi')
            ->assertSee('Eski portal bilgileri okunamadı')
            ->assertSee('Portal Linki ve Kodu Yenile');
    }

    public function test_stock_needs_include_inactive_distributed_materials_and_can_order_them(): void
    {
        [$branch, $user, $staff] = $this->identity('NEED');
        $product = $this->product($branch, 'Merkezden Gelen Un', 2);
        $product->update(['is_active' => false, 'min_stock_level' => 10]);
        $supplier = Supplier::create(['branch_id' => $branch->id, 'name' => 'Un Tedarikçisi', 'is_active' => true]);

        $this->actingAsStaff($user, $staff)
            ->get(route('purchasing.index', ['tab' => 'stock-needs']))
            ->assertOk()
            ->assertSee('Şube Stok İhtiyaçları')
            ->assertSee('Merkezden Gelen Un')
            ->assertSee('Sipariş Gerekli');

        $this->actingAsStaff($user, $staff)->post(route('purchasing.orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 8, 'unit_price' => 20, 'tax_rate' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('purchase_order_items', ['product_id' => $product->id, 'quantity' => 8]);
    }

    public function test_restaurant_report_contains_stock_purchasing_and_production_sections(): void
    {
        [$branch, $user, $staff] = $this->identity('REPORT');
        $product = $this->product($branch, 'Kritik Pirinç', 0);
        $product->update(['min_stock_level' => 5]);

        $this->actingAsStaff($user, $staff)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Stok, Tedarik ve Üretim')
            ->assertSee('Kritik Stok Listesi')
            ->assertSee('Kritik Pirinç')
            ->assertSee('Tedarikçi Harcamaları')
            ->assertSee('Üretim ve Reçete Performansı');
    }

    public function test_supplier_and_purchase_order_are_created_for_current_branch(): void
    {
        [$branch, $user, $staff] = $this->identity('A');
        $product = $this->product($branch, 'Un', 10);

        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.suppliers.store'), [
                'name' => 'Gıda Tedarik AŞ',
                'tax_number' => '1234567890',
                'email' => 'tedarik@example.test',
            ])
            ->assertRedirect();
        $supplier = Supplier::firstOrFail();

        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.orders.store'), [
                'supplier_id' => $supplier->id,
                'order_date' => now()->format('Y-m-d'),
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 20,
                    'tax_rate' => 20,
                ]],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::with('items')->firstOrFail();
        $this->assertSame($branch->id, $order->branch_id);
        $this->assertSame('draft', $order->status);
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('40.00', $order->tax_total);
        $this->assertSame('240.00', $order->total);
        $this->assertSame('10.000', $order->items->first()->quantity);
    }

    public function test_order_cannot_contain_another_branch_product(): void
    {
        [$branchA, $userA, $staffA] = $this->identity('B');
        [$branchB] = $this->identity('C');
        $foreignProduct = $this->product($branchB, 'Yabancı Ürün', 5);
        $supplier = Supplier::create(['branch_id' => $branchA->id, 'name' => 'Yerel Tedarikçi', 'is_active' => true]);

        $this->actingAsStaff($userA, $staffA)
            ->post(route('purchasing.orders.store'), [
                'supplier_id' => $supplier->id,
                'order_date' => now()->format('Y-m-d'),
                'items' => [[
                    'product_id' => $foreignProduct->id,
                    'quantity' => 1,
                    'unit_price' => 10,
                    'tax_rate' => 0,
                ]],
            ])
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_partial_and_full_receipts_increment_stock_exactly_once(): void
    {
        [$branch, $user, $staff] = $this->identity('D');
        $product = $this->product($branch, 'Şeker', 5);
        $order = $this->order($branch, $user, $product, 10, 15);

        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.orders.place', $order))
            ->assertRedirect();
        $item = $order->items()->firstOrFail();

        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.orders.receive', $order), [
                'quantities' => [$item->id => 4],
                'supplier_invoice_number' => 'IRS-1',
            ])
            ->assertRedirect();

        $this->assertSame('partial', $order->fresh()->status);
        $this->assertSame('9.00', $product->fresh()->stock_quantity);
        $this->assertSame('4.000', $item->fresh()->received_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'purchase_receipt',
            'quantity' => 4,
        ]);

        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.orders.receive', $order), [
                'quantities' => [$item->id => 6],
                'supplier_invoice_number' => 'IRS-2',
            ])
            ->assertRedirect();

        $this->assertSame('received', $order->fresh()->status);
        $this->assertSame('15.00', $product->fresh()->stock_quantity);
        $this->assertSame('10.000', $item->fresh()->received_quantity);
        $this->assertDatabaseCount('purchase_receipts', 2);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_over_receipt_is_rejected_without_changing_stock(): void
    {
        [$branch, $user, $staff] = $this->identity('E');
        $product = $this->product($branch, 'Yağ', 3);
        $order = $this->order($branch, $user, $product, 5, 100);
        $this->actingAsStaff($user, $staff)->post(route('purchasing.orders.place', $order));
        $item = $order->items()->firstOrFail();

        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.orders.receive', $order), [
                'quantities' => [$item->id => 5.001],
            ])
            ->assertSessionHasErrors("quantities.{$item->id}");

        $this->assertSame('3.00', $product->fresh()->stock_quantity);
        $this->assertSame('0.000', $item->fresh()->received_quantity);
        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_received_order_cannot_be_cancelled_and_actions_are_audited(): void
    {
        [$branch, $user, $staff] = $this->identity('F');
        $product = $this->product($branch, 'Pirinç', 0);
        $order = $this->order($branch, $user, $product, 2, 50);
        AuditLog::query()->delete();

        $this->actingAsStaff($user, $staff)->post(route('purchasing.orders.place', $order))->assertRedirect();
        $item = $order->items()->firstOrFail();
        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.orders.receive', $order), ['quantities' => [$item->id => 2]])
            ->assertRedirect();
        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.orders.cancel', $order))
            ->assertSessionHasErrors('order');

        $this->assertSame('received', $order->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order.placed', 'category' => 'purchasing']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order.received', 'category' => 'purchasing']);
    }

    /**
     * @return array{Branch, User, StaffProfile}
     */
    private function identity(string $suffix): array
    {
        $branch = Branch::create(['name' => "Satın Alma Şubesi {$suffix}", 'code' => "PUR-{$suffix}", 'is_active' => true]);
        $user = User::create([
            'branch_id' => $branch->id,
            'name' => "Kullanıcı {$suffix}",
            'email' => "purchase-{$suffix}@example.test",
            'password' => 'secret-password',
            'is_admin' => false,
        ]);
        $staff = StaffProfile::create([
            'branch_id' => $branch->id,
            'name' => "Satın Alma Personeli {$suffix}",
            'role' => 'Kasa',
            'pin_code' => 'migrated',
            'pin_hash' => bcrypt('1234'),
            'pin_length' => 4,
            'avatar_color' => 'orange',
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

    private function product(Branch $branch, string $name, float $stock): Product
    {
        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => "{$name} Kategorisi",
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
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
            'unit' => 'kg',
            'track_stock' => true,
            'is_active' => true,
        ]);
    }

    private function order(Branch $branch, User $user, Product $product, float $quantity, float $price): PurchaseOrder
    {
        $supplier = Supplier::create([
            'branch_id' => $branch->id,
            'name' => 'Test Tedarikçi '.Str::upper(Str::random(4)),
            'is_active' => true,
        ]);
        $order = PurchaseOrder::create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $user->id,
            'order_number' => 'SAT-TEST-'.Str::upper(Str::random(6)),
            'status' => 'draft',
            'created_by_name' => $user->name,
            'order_date' => now()->toDateString(),
            'subtotal' => $quantity * $price,
            'tax_total' => 0,
            'total' => $quantity * $price,
        ]);
        $order->items()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => $product->unit,
            'quantity' => $quantity,
            'received_quantity' => 0,
            'unit_price' => $price,
            'tax_rate' => 0,
            'line_subtotal' => $quantity * $price,
            'line_tax' => 0,
            'line_total' => $quantity * $price,
        ]);

        return $order;
    }
}
