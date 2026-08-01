<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_restaurant_login_records_audit_details(): void
    {
        $branch = Branch::create([
            'name' => 'Merkez',
            'code' => 'MERKEZ-LOGIN',
            'is_active' => true,
        ]);
        $user = User::create([
            'branch_id' => $branch->id,
            'name' => 'Kasa Kullanıcısı',
            'email' => 'kasa-login@example.test',
            'restaurant_id' => 'REST-LOGIN',
            'password' => 'secret-password',
            'is_admin' => false,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('User-Agent', 'AdisyonTestBrowser/1.0')
            ->post('/login', [
                'restaurant_id' => 'REST-LOGIN',
                'password' => 'secret-password',
                'remember' => '1',
            ])
            ->assertRedirect(route('dashboard'));

        $log = LoginLog::sole();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($branch->id, $log->branch_id);
        $this->assertSame('Kasa Kullanıcısı', $log->user_name);
        $this->assertSame('kasa-login@example.test', $log->user_email);
        $this->assertSame('REST-LOGIN', $log->restaurant_id);
        $this->assertSame('restaurant', $log->portal);
        $this->assertSame('web', $log->guard);
        $this->assertSame('203.0.113.10', $log->ip_address);
        $this->assertSame('AdisyonTestBrowser/1.0', $log->user_agent);
        $this->assertTrue($log->remember_me);
        $this->assertNotNull($log->logged_in_at);
    }

    public function test_successful_admin_login_is_marked_as_admin_portal(): void
    {
        $admin = User::create([
            'name' => 'Sistem Yöneticisi',
            'email' => 'admin-login@example.test',
            'restaurant_id' => 'REST-ADMIN-LOGIN',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::10'])
            ->withHeader('User-Agent', 'AdminBrowser/2.0')
            ->post('/admin/login', [
                'email' => 'admin-login@example.test',
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $log = LoginLog::sole();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertNull($log->branch_id);
        $this->assertSame('admin', $log->portal);
        $this->assertSame('2001:db8::10', $log->ip_address);
        $this->assertFalse($log->remember_me);
    }

    public function test_failed_login_does_not_create_a_success_log(): void
    {
        $this->post('/login', [
            'restaurant_id' => 'REST-MISSING',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('restaurant_id');

        $this->assertDatabaseCount('login_logs', 0);
    }

    public function test_restaurant_login_limits_are_isolated_by_restaurant_id(): void
    {
        $client = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10']);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $client->post('/login', [
                'restaurant_id' => 'RATE-LIMIT-A',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $client->post('/login', [
            'restaurant_id' => 'RATE-LIMIT-A',
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $client->post('/login', [
            'restaurant_id' => 'RATE-LIMIT-B',
            'password' => 'wrong-password',
        ])->assertRedirect();
    }
}
