<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSecurityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_login_logs_by_branch_portal_and_search(): void
    {
        $admin = $this->admin();
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');

        $this->loginLog($branchA, 'Aranan Kullanıcı', 'aranan@example.test', 'restaurant');
        $this->loginLog($branchB, 'Diğer Kullanıcı', 'diger@example.test', 'restaurant');
        $this->loginLog(null, 'Central Admin', 'central@example.test', 'admin');

        $this->actingAs($admin)
            ->get(route('admin.logs.index', [
                'tab' => 'logins',
                'branch_id' => $branchA->id,
                'portal' => 'restaurant',
                'search' => 'Aranan',
            ]))
            ->assertOk()
            ->assertSee('Aranan Kullanıcı')
            ->assertDontSee('Diğer Kullanıcı')
            ->assertDontSee('central@example.test');
    }

    public function test_admin_can_view_filtered_device_logs(): void
    {
        $admin = $this->admin();
        $branch = $this->branch('CIHAZ');
        $device = Device::create([
            'branch_id' => $branch->id,
            'device_code' => 'KASA-FILTER',
            'device_guid' => (string) Str::uuid(),
        ]);
        DeviceLog::create([
            'device_id' => $device->id,
            'event_type' => 'HeartbeatPing',
            'ip_address' => '203.0.113.20',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.logs.index', [
                'tab' => 'devices',
                'branch_id' => $branch->id,
                'search' => 'KASA-FILTER',
                'ip' => '203.0.113.20',
            ]))
            ->assertOk()
            ->assertSee('KASA-FILTER')
            ->assertSee('HeartbeatPing')
            ->assertSee('203.0.113.20');
    }

    public function test_login_log_csv_export_is_utf8_and_formula_safe(): void
    {
        $admin = $this->admin();
        $branch = $this->branch('CSV');
        $this->loginLog($branch, '=FORMULA', 'csv@example.test', 'restaurant');

        $response = $this->actingAs($admin)
            ->get(route('admin.logs.export', ['tab' => 'logins']));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload();

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString("'=FORMULA", $content);
        $this->assertStringContainsString('csv@example.test', $content);
    }

    public function test_admin_can_filter_and_export_audit_logs(): void
    {
        $admin = $this->admin();
        $branch = $this->branch('AUDIT');
        AuditLog::create([
            'actor_user_id' => $admin->id,
            'branch_id' => $branch->id,
            'actor_user_name' => 'Denetim Yöneticisi',
            'action' => 'product.updated',
            'category' => 'catalog',
            'subject_type' => 'App\\Models\\Product',
            'subject_id' => 99,
            'subject_label' => '=Tehlikeli Ürün',
            'description' => 'Ürün fiyatı güncellendi.',
            'old_values' => ['price' => '10.00'],
            'new_values' => ['price' => '20.00'],
            'ip_address' => '203.0.113.55',
            'request_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.logs.index', [
                'tab' => 'audits',
                'branch_id' => $branch->id,
                'category' => 'catalog',
                'search' => 'product.updated',
            ]))
            ->assertOk()
            ->assertSee('Denetim Yöneticisi')
            ->assertSee('product.updated')
            ->assertSee('İşlem Geçmişi');

        $response = $this->actingAs($admin)
            ->get(route('admin.logs.export', ['tab' => 'audits', 'category' => 'catalog']));

        $response->assertOk()->assertDownload();
        $content = $response->streamedContent();

        $this->assertStringContainsString('product.updated', $content);
        $this->assertStringContainsString("'=Tehlikeli Ürün", $content);
    }

    public function test_non_admin_cannot_open_security_logs(): void
    {
        $branch = $this->branch('USER');
        $user = User::create([
            'branch_id' => $branch->id,
            'name' => 'Restoran Kullanıcısı',
            'email' => 'restaurant-user@example.test',
            'password' => 'secret-password',
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.logs.index'))
            ->assertRedirect(route('admin.login'));
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Sistem Yöneticisi',
            'email' => 'security-admin@example.test',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);
    }

    private function branch(string $suffix): Branch
    {
        return Branch::create([
            'name' => "Şube {$suffix}",
            'code' => "SEC-{$suffix}",
            'is_active' => true,
        ]);
    }

    private function loginLog(?Branch $branch, string $name, string $email, string $portal): LoginLog
    {
        return LoginLog::create([
            'branch_id' => $branch?->id,
            'user_name' => $name,
            'user_email' => $email,
            'restaurant_id' => $branch ? "REST-{$branch->code}" : 'REST-ADMIN',
            'portal' => $portal,
            'guard' => 'web',
            'ip_address' => '203.0.113.10',
            'user_agent' => 'SecurityLogTest/1.0',
            'remember_me' => false,
            'logged_in_at' => now(),
        ]);
    }
}
