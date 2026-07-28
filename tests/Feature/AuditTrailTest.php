<?php

namespace Tests\Feature;

use App\Enums\CheckStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_change_records_actor_staff_branch_ip_and_changed_values(): void
    {
        [$branch, $user, $staff] = $this->restaurantIdentity();
        $product = $this->product($branch);
        AuditLog::query()->delete();

        $this->actingAsStaff($user, $staff)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->put(route('products.update', $product), [
                'category_id' => $product->category_id,
                'name' => 'Yeni Ürün Adı',
                'price' => 75.50,
                'discounted_price' => null,
                'sku' => 'SKU-NEW',
                'description' => null,
                'kitchen_department' => null,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $log = AuditLog::where('action', 'product.updated')->firstOrFail();

        $this->assertSame($user->id, $log->actor_user_id);
        $this->assertSame($staff->id, $log->actor_staff_profile_id);
        $this->assertSame($branch->id, $log->branch_id);
        $this->assertSame('203.0.113.42', $log->ip_address);
        $this->assertSame('Test Personeli', $log->actor_staff_name);
        $this->assertSame('Test Ürünü', $log->old_values['name']);
        $this->assertSame('Yeni Ürün Adı', $log->new_values['name']);
        $this->assertNotEmpty($log->request_id);
    }

    public function test_sensitive_setting_and_staff_pin_values_are_redacted(): void
    {
        [$branch, $user, $staff] = $this->restaurantIdentity();
        $this->actingAsStaff($user, $staff);
        AuditLog::query()->delete();

        Setting::set('integration_api_key', 'super-secret-value', 'integration', $branch->id);
        $staff->update([
            'pin_code' => 'migrated',
            'pin_hash' => bcrypt('9876'),
            'pin_length' => 4,
        ]);

        $payload = AuditLog::query()
            ->get()
            ->flatMap(fn (AuditLog $log): array => [$log->old_values, $log->new_values])
            ->toJson();

        $this->assertStringNotContainsString('super-secret-value', $payload);
        $this->assertStringNotContainsString('9876', $payload);
        $this->assertStringContainsString('[REDACTED]', $payload);
    }

    public function test_discount_action_records_a_semantic_sales_event(): void
    {
        [$branch, $user, $staff] = $this->restaurantIdentity();
        $product = $this->product($branch);
        $check = Check::create([
            'branch_id' => $branch->id,
            'waiter_id' => $user->id,
            'check_number' => 'CHK-AUDIT',
            'sync_uuid' => (string) Str::uuid(),
            'status' => CheckStatus::Open,
            'opened_at' => now(),
        ]);
        CheckItem::create([
            'branch_id' => $branch->id,
            'check_id' => $check->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 50,
            'total_price' => 100,
            'sync_uuid' => (string) Str::uuid(),
        ]);
        AuditLog::query()->delete();

        $this->actingAsStaff($user, $staff)
            ->post(route('checks.actions.discount', $check), [
                'type' => 'percentage',
                'value' => 10,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'check.discount_applied',
            'category' => 'sales',
            'subject_id' => $check->id,
            'actor_user_id' => $user->id,
            'actor_staff_profile_id' => $staff->id,
        ]);

        $log = AuditLog::where('action', 'check.discount_applied')->firstOrFail();
        $this->assertSame(10, $log->new_values['discount_value']);
        $this->assertSame('percentage', $log->new_values['discount_type']);
    }

    public function test_background_model_changes_do_not_create_user_audit_noise(): void
    {
        $branch = Branch::create([
            'name' => 'Arka Plan Şubesi',
            'code' => 'AUDIT-BG',
            'is_active' => true,
        ]);

        Category::create([
            'branch_id' => $branch->id,
            'name' => 'Arka Plan Kategorisi',
            'slug' => 'arka-plan',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * @return array{Branch, User, StaffProfile}
     */
    private function restaurantIdentity(): array
    {
        $branch = Branch::create([
            'name' => 'Denetim Şubesi',
            'code' => 'AUDIT-01',
            'is_active' => true,
        ]);
        $user = User::create([
            'branch_id' => $branch->id,
            'name' => 'Denetim Kullanıcısı',
            'email' => 'audit@example.test',
            'password' => 'secret-password',
            'is_admin' => false,
        ]);
        $staff = StaffProfile::create([
            'branch_id' => $branch->id,
            'name' => 'Test Personeli',
            'role' => 'Yönetici',
            'pin_code' => 'migrated',
            'pin_hash' => bcrypt('1234'),
            'pin_length' => 4,
            'avatar_color' => 'indigo',
            'is_active' => true,
        ]);

        return [$branch, $user, $staff];
    }

    private function product(Branch $branch): Product
    {
        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => 'Test Kategorisi',
            'slug' => 'test-kategorisi',
            'is_active' => true,
        ]);

        return Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Test Ürünü',
            'slug' => 'test-urunu',
            'sku' => 'SKU-OLD',
            'price' => 50,
            'stock_quantity' => 10,
            'min_stock_level' => 1,
            'unit' => 'adet',
            'track_stock' => true,
            'is_active' => true,
        ]);
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
}
