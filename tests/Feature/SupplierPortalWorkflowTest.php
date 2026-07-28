<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\Supplier;
use App\Models\SupplierProductSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierPortalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_persistent_link_and_four_digit_code(): void
    {
        [$branch, $user, $staff] = $this->identity('SETUP');
        $supplier = $this->supplier($branch);

        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.supplier-portal.setup', $supplier))
            ->assertRedirect(route('purchasing.index', ['tab' => 'supplier-products']));

        $supplier->refresh();
        $this->assertTrue($supplier->portal_enabled);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $supplier->portal_code);
        $this->assertSame(64, strlen($supplier->portal_token));
        $this->assertNotSame($supplier->portal_code, $supplier->getRawOriginal('portal_code'));
        $this->assertNotSame($supplier->portal_token, $supplier->getRawOriginal('portal_token'));

        $this->get(route('supplier-portal.show', $supplier->portal_token))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee('Erişim kodunu girin')
            ->assertSee('viewport-fit=cover', false);
    }

    public function test_supplier_code_unlocks_reusable_product_portal(): void
    {
        [$branch, $user, $staff] = $this->identity('CODE');
        $supplier = $this->supplier($branch);
        $this->actingAsStaff($user, $staff)->post(route('purchasing.supplier-portal.setup', $supplier));
        $supplier->refresh();
        $token = $supplier->portal_token;

        $this->post(route('supplier-portal.verify', $token), ['code' => '0000'])
            ->assertSessionHasErrors('code');
        $this->post(route('supplier-portal.verify', $token), ['code' => $supplier->portal_code])
            ->assertRedirect(route('supplier-portal.show', $token));
        $this->get(route('supplier-portal.show', $token))
            ->assertOk()
            ->assertSee('Ürün bilgisi ekleyin');

        $payload = $this->productPayload('Osmancık Pirinç');
        $this->post(route('supplier-portal.products.store', $token), $payload)
            ->assertRedirect(route('supplier-portal.show', $token));
        $this->post(route('supplier-portal.products.store', $token), $this->productPayload('Ayçiçek Yağı'))
            ->assertRedirect(route('supplier-portal.show', $token));

        $this->assertDatabaseCount('supplier_product_submissions', 2);
        $this->assertDatabaseCount('supplier_product_submission_items', 2);
        $submission = SupplierProductSubmission::query()->oldest('id')->with('items')->firstOrFail();
        $this->assertSame('pending', $submission->status);
        $this->assertSame('Osmancık Pirinç', $submission->items->first()->product_name);
        $this->assertSame('125.5000', $submission->items->first()->unit_price);
        $this->assertSame('10.000', $submission->items->first()->minimum_order_quantity);
    }

    public function test_disabled_portal_cannot_be_viewed_or_used(): void
    {
        [$branch, $user, $staff] = $this->identity('DISABLED');
        $supplier = $this->supplier($branch);
        $this->actingAsStaff($user, $staff)->post(route('purchasing.supplier-portal.setup', $supplier));
        $supplier->refresh();
        $token = $supplier->portal_token;
        $code = $supplier->portal_code;
        $this->post(route('supplier-portal.verify', $token), ['code' => $code]);

        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.supplier-portal.toggle', $supplier))
            ->assertRedirect();

        $this->get(route('supplier-portal.show', $token))
            ->assertOk()
            ->assertSee('Portal kullanıma kapalı');
        $this->post(route('supplier-portal.products.store', $token), $this->productPayload('Kapalı Ürün'))
            ->assertSessionHasErrors('portal');
        $this->assertDatabaseCount('supplier_product_submissions', 0);
    }

    public function test_regenerating_credentials_invalidates_old_link_and_code(): void
    {
        [$branch, $user, $staff] = $this->identity('REGEN');
        $supplier = $this->supplier($branch);
        $this->actingAsStaff($user, $staff)->post(route('purchasing.supplier-portal.setup', $supplier));
        $supplier->refresh();
        $oldToken = $supplier->portal_token;
        $oldCode = $supplier->portal_code;

        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.supplier-portal.regenerate', $supplier))
            ->assertRedirect();
        $supplier->refresh();

        $this->assertNotSame($oldToken, $supplier->portal_token);
        $this->assertNotSame($oldCode, $supplier->portal_code);
        $this->get(route('supplier-portal.show', $oldToken))->assertNotFound();
        $this->post(route('supplier-portal.verify', $supplier->portal_token), ['code' => $oldCode])
            ->assertSessionHasErrors('code');
    }

    public function test_management_can_approve_or_reject_product_information(): void
    {
        [$branch, $user, $staff] = $this->identity('REVIEW');
        $supplier = $this->supplier($branch);
        $this->actingAsStaff($user, $staff)->post(route('purchasing.supplier-portal.setup', $supplier));
        $supplier->refresh();
        $this->post(route('supplier-portal.verify', $supplier->portal_token), ['code' => $supplier->portal_code]);
        $this->post(route('supplier-portal.products.store', $supplier->portal_token), $this->productPayload('Onay Ürünü'));
        $this->post(route('supplier-portal.products.store', $supplier->portal_token), $this->productPayload('Red Ürünü'));
        [$approve, $reject] = SupplierProductSubmission::query()->orderBy('id')->get();
        AuditLog::query()->delete();

        $this->actingAsStaff($user, $staff)
            ->get(route('purchasing.index', ['tab' => 'supplier-products']))
            ->assertOk()
            ->assertSee('Onay Ürünü')
            ->assertSee('Red Ürünü');
        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.supplier-portal.approve', $approve), ['review_notes' => 'Bilgiler doğrulandı.'])
            ->assertRedirect();
        $this->actingAsStaff($user, $staff)
            ->post(route('purchasing.supplier-portal.reject', $reject), ['review_notes' => 'Barkod düzeltilmeli.'])
            ->assertRedirect();

        $this->assertSame('approved', $approve->fresh()->status);
        $this->assertSame('rejected', $reject->fresh()->status);
        $this->assertSame('Barkod düzeltilmeli.', $reject->fresh()->review_notes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier_product_submission.approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier_product_submission.rejected']);
    }

    public function test_another_branch_cannot_manage_supplier_submission(): void
    {
        [$branchA] = $this->identity('BRANCH-A');
        [$branchB, $userB, $staffB] = $this->identity('BRANCH-B');
        $supplier = $this->supplier($branchA);
        $submission = SupplierProductSubmission::create([
            'branch_id' => $branchA->id,
            'supplier_id' => $supplier->id,
            'submission_number' => 'TU-TEST-'.Str::upper(Str::random(6)),
            'status' => 'pending',
            'contact_name' => 'Yetkili',
            'submitted_at' => now(),
        ]);

        $this->actingAsStaff($userB, $staffB)
            ->post(route('purchasing.supplier-portal.approve', $submission))
            ->assertNotFound();
        $this->assertSame('pending', $submission->fresh()->status);
        $this->assertSame($branchB->id, $userB->branch_id);
    }

    /**
     * @return array{Branch, User, StaffProfile}
     */
    private function identity(string $suffix): array
    {
        $branch = Branch::create([
            'name' => "Portal Şubesi {$suffix}",
            'code' => Str::limit("PORTAL-{$suffix}", 50, ''),
            'is_active' => true,
        ]);
        $user = User::create([
            'branch_id' => $branch->id,
            'name' => "Kullanıcı {$suffix}",
            'email' => Str::lower("portal-{$suffix}@example.test"),
            'password' => 'secret-password',
            'is_admin' => false,
        ]);
        $staff = StaffProfile::create([
            'branch_id' => $branch->id,
            'name' => "Portal Personeli {$suffix}",
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

    private function supplier(Branch $branch): Supplier
    {
        return Supplier::create([
            'branch_id' => $branch->id,
            'name' => 'Portal Tedarikçisi '.Str::upper(Str::random(4)),
            'contact_person' => 'Tedarik Yetkilisi',
            'email' => 'supplier@example.test',
            'phone' => '05551112233',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(string $productName): array
    {
        return [
            'contact_name' => 'Tedarik Yetkilisi',
            'contact_email' => 'supplier@example.test',
            'contact_phone' => '05551112233',
            'supplier_notes' => 'Güncel ürün bilgileri.',
            'items' => [[
                'product_name' => $productName,
                'supplier_sku' => 'TED-001',
                'barcode' => '8690000000001',
                'brand' => 'Test Marka',
                'unit' => 'kg',
                'package_description' => '10 x 1 kg',
                'unit_price' => 125.5,
                'tax_rate' => 10,
                'minimum_order_quantity' => 10,
                'delivery_days' => 2,
                'notes' => 'Birinci kalite.',
            ]],
        ];
    }
}
