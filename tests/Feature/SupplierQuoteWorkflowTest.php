<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StaffProfile;
use App\Models\Supplier;
use App\Models\SupplierQuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierQuoteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_generate_a_hashed_supplier_quote_link(): void
    {
        [$branch, $user, $staff] = $this->identity('LINK');
        $supplier = $this->supplier($branch, 'Link Tedarikçisi');

        $response = $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.quotes.store'), [
                'supplier_id' => $supplier->id,
                'expires_in_days' => 7,
                'message' => 'Haftalık ürün teklifinizi iletin.',
            ])
            ->assertRedirect(route('purchasing.index', ['tab' => 'quotes']))
            ->assertSessionHas('generated_quote_url');

        $url = $response->getSession()->get('generated_quote_url');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $quoteRequest = SupplierQuoteRequest::firstOrFail();

        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $quoteRequest->getRawOriginal('token_hash'));
        $this->assertStringNotContainsString($token, $quoteRequest->getRawOriginal('token_hash'));
        $this->get($url)
            ->assertOk()
            ->assertSee($supplier->name)
            ->assertSee('Ürün teklifinizi iletin');
    }

    public function test_supplier_can_submit_quote_only_once(): void
    {
        [$branch] = $this->identity('SUBMIT');
        $product = $this->product($branch, 'Domates');
        [$quoteRequest, $token] = $this->quoteRequest($branch);

        $payload = [
            'contact_name' => 'Ayşe Tedarikçi',
            'contact_email' => 'ayse@example.test',
            'contact_phone' => '05550000000',
            'expected_delivery_date' => now()->addDays(2)->toDateString(),
            'supplier_notes' => 'Sabah teslim edilir.',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_price' => 25,
                'tax_rate' => 20,
                'notes' => 'Yerli ürün',
            ]],
        ];

        $this->post(route('supplier-quotes.public.submit', $token), $payload)
            ->assertRedirect(route('supplier-quotes.public.show', $token));

        $quoteRequest->refresh();
        $item = $quoteRequest->items()->firstOrFail();
        $this->assertSame('submitted', $quoteRequest->status);
        $this->assertSame('Ayşe Tedarikçi', $quoteRequest->contact_name);
        $this->assertNotNull($quoteRequest->submitted_at);
        $this->assertSame('250.00', $item->line_subtotal);
        $this->assertSame('50.00', $item->line_tax);
        $this->assertSame('300.00', $item->line_total);

        $this->post(route('supplier-quotes.public.submit', $token), $payload)
            ->assertSessionHasErrors('quote');
        $this->assertDatabaseCount('supplier_quote_items', 1);
    }

    public function test_supplier_cannot_quote_another_branch_product(): void
    {
        [$branchA] = $this->identity('BRANCH-A');
        [$branchB] = $this->identity('BRANCH-B');
        $foreignProduct = $this->product($branchB, 'Yabancı Ürün');
        [$quoteRequest, $token] = $this->quoteRequest($branchA);

        $this->post(route('supplier-quotes.public.submit', $token), [
            'contact_name' => 'Yetkili',
            'items' => [[
                'product_id' => $foreignProduct->id,
                'quantity' => 1,
                'unit_price' => 10,
                'tax_rate' => 0,
            ]],
        ])->assertSessionHasErrors('items.0.product_id');

        $this->assertSame('open', $quoteRequest->fresh()->status);
        $this->assertDatabaseCount('supplier_quote_items', 0);
    }

    public function test_approved_quote_creates_one_draft_purchase_order(): void
    {
        [$branch, $user, $staff] = $this->identity('APPROVE');
        $product = $this->product($branch, 'Pirinç');
        [$quoteRequest, $token] = $this->quoteRequest($branch);
        $this->post(route('supplier-quotes.public.submit', $token), [
            'contact_name' => 'Teklif Yetkilisi',
            'expected_delivery_date' => now()->addDays(3)->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 5,
                'unit_price' => 40,
                'tax_rate' => 10,
            ]],
        ]);
        AuditLog::query()->delete();

        $this->actingAsStaff($user, $staff)
            ->get(route('purchasing.index', ['tab' => 'quotes']))
            ->assertOk()
            ->assertSee('Teklif Yetkilisi')
            ->assertSee('₺220,00');

        $response = $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.quotes.approve', $quoteRequest), [
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => now()->addDays(3)->toDateString(),
            ]);

        $order = PurchaseOrder::with('items')->firstOrFail();
        $response->assertRedirect(route('purchasing.show', $order));
        $this->assertSame('draft', $order->status);
        $this->assertSame($quoteRequest->supplier_id, $order->supplier_id);
        $this->assertSame('220.00', $order->total);
        $this->assertSame('approved', $quoteRequest->fresh()->status);
        $this->assertSame($order->id, $quoteRequest->fresh()->purchase_order_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'supplier_quote_request.approved',
            'category' => 'purchasing',
        ]);

        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.quotes.approve', $quoteRequest), [
                'order_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('quote');
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_expired_quote_link_cannot_be_submitted(): void
    {
        [$branch] = $this->identity('EXPIRED');
        $product = $this->product($branch, 'Yağ');
        [$quoteRequest, $token] = $this->quoteRequest($branch, now()->subMinute());

        $this->get(route('supplier-quotes.public.show', $token))
            ->assertOk()
            ->assertSee('Bağlantı kullanılamıyor');
        $this->post(route('supplier-quotes.public.submit', $token), [
            'contact_name' => 'Yetkili',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 10,
            ]],
        ])->assertSessionHasErrors('quote');

        $this->assertSame('expired', $quoteRequest->fresh()->status);
        $this->assertDatabaseCount('supplier_quote_items', 0);
    }

    /**
     * @return array{Branch, User, StaffProfile}
     */
    private function identity(string $suffix): array
    {
        $branch = Branch::create([
            'name' => "Teklif Şubesi {$suffix}",
            'code' => Str::limit("QUOTE-{$suffix}", 50, ''),
            'is_active' => true,
        ]);
        $user = User::create([
            'branch_id' => $branch->id,
            'name' => "Kullanıcı {$suffix}",
            'email' => Str::lower("quote-{$suffix}@example.test"),
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

    private function supplier(Branch $branch, string $name = 'Test Tedarikçi'): Supplier
    {
        return Supplier::create([
            'branch_id' => $branch->id,
            'name' => $name.' '.Str::upper(Str::random(4)),
            'contact_person' => 'Tedarik Yetkilisi',
            'email' => 'supplier@example.test',
            'phone' => '05551112233',
            'is_active' => true,
        ]);
    }

    private function product(Branch $branch, string $name): Product
    {
        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => "{$name} Kategorisi",
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'is_active' => true,
        ]);

        return Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'sku' => 'SKU-'.Str::upper(Str::random(5)),
            'price' => 10,
            'stock_quantity' => 0,
            'min_stock_level' => 0,
            'unit' => 'kg',
            'track_stock' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{SupplierQuoteRequest, string}
     */
    private function quoteRequest(Branch $branch, mixed $expiresAt = null): array
    {
        $supplier = $this->supplier($branch);
        $token = Str::random(64);
        $quoteRequest = SupplierQuoteRequest::create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'request_number' => 'TF-TEST-'.Str::upper(Str::random(6)),
            'token_hash' => hash('sha256', $token),
            'status' => 'open',
            'requested_by_name' => 'Test Personeli',
            'expires_at' => $expiresAt ?? now()->addWeek(),
        ]);

        return [$quoteRequest, $token];
    }
}
