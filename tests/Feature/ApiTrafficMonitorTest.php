<?php

namespace Tests\Feature;

use App\Models\ApiTrafficLog;
use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTrafficMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_api_requests_are_captured_and_sensitive_values_are_redacted(): void
    {
        [$branch, $user, $staff] = $this->identity();

        $response = $this->withHeader('Authorization', 'Bearer should-not-be-stored')
            ->postJson('/api/v1/waiter/auth/login', [
                'restaurant_id' => $user->restaurant_id,
                'password' => 'restaurant-secret',
                'profile_id' => $staff->id,
                'pin' => '1234',
                'device_name' => 'Flutter Monitor Test',
            ])
            ->assertOk();

        $requestId = $response->headers->get('X-Request-ID');
        $this->assertNotNull($requestId);
        $this->assertTrue(Str::isUuid($requestId));
        $this->assertStringStartsWith('wtr_', $response->json('data.access_token'));

        $log = ApiTrafficLog::query()->sole();

        $this->assertSame($requestId, $log->request_id);
        $this->assertSame('POST', $log->method);
        $this->assertSame('/api/v1/waiter/auth/login', $log->path);
        $this->assertSame('api.waiter.auth.login', $log->route_name);
        $this->assertSame(200, $log->status_code);
        $this->assertSame($branch->id, $log->branch_id);
        $this->assertSame($staff->id, $log->staff_profile_id);
        $this->assertSame($staff->name, $log->staff_name);
        $this->assertSame($user->restaurant_id, $log->restaurant_id);
        $this->assertSame('[REDACTED]', $log->request_headers['authorization']);
        $this->assertSame('[REDACTED]', $log->request_payload['password']);
        $this->assertSame('[REDACTED]', $log->request_payload['pin']);
        $this->assertSame('[REDACTED]', $log->response_payload['data']['access_token']);
    }

    public function test_validation_errors_are_captured_without_exposing_passwords(): void
    {
        $this->postJson('/api/v1/waiter/auth/profiles', [
            'restaurant_id' => 'REST-UNKNOWN',
            'password' => 'super-secret',
        ])->assertUnprocessable();

        $log = ApiTrafficLog::query()->sole();

        $this->assertSame(422, $log->status_code);
        $this->assertSame('/api/v1/waiter/auth/profiles', $log->path);
        $this->assertSame('[REDACTED]', $log->request_payload['password']);
        $this->assertSame('REST-UNKNOWN', $log->request_payload['restaurant_id']);
        $this->assertArrayHasKey('errors', $log->response_payload);
    }

    public function test_only_admins_can_view_and_filter_the_live_traffic_stream(): void
    {
        [$branch] = $this->identity();
        $admin = User::create([
            'name' => 'Sistem Yöneticisi',
            'email' => 'api-monitor-admin@example.test',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);
        $nonAdmin = User::create([
            'branch_id' => $branch->id,
            'restaurant_id' => 'REST-NON-ADMIN',
            'name' => 'Restoran Kullanıcısı',
            'email' => 'api-monitor-user@example.test',
            'password' => 'secret-password',
            'is_admin' => false,
        ]);

        $success = $this->trafficLog($branch, 200, '/api/v1/waiter/products');
        $this->trafficLog($branch, 500, '/api/v1/waiter/orders');

        $this->actingAs($nonAdmin)
            ->get(route('admin.api-traffic.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($nonAdmin)
            ->getJson(route('admin.api-traffic.stream'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.api-traffic.index'))
            ->assertOk()
            ->assertSee('Canlı API Trafiği');

        $stream = $this->actingAs($admin)
            ->getJson(route('admin.api-traffic.stream', [
                'branch_id' => $branch->id,
                'status' => '2xx',
                'search' => 'products',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'logs')
            ->assertJsonPath('logs.0.id', $success->id)
            ->assertJsonPath('logs.0.status_code', 200)
            ->assertJsonPath('logs.0.branch.id', $branch->id);

        $this->assertStringContainsString('no-store', (string) $stream->headers->get('Cache-Control'));
    }

    /** @return array{Branch, User, StaffProfile} */
    private function identity(): array
    {
        $branch = Branch::create([
            'name' => 'API Monitor Şubesi',
            'code' => 'API-MONITOR',
            'is_active' => true,
        ]);
        $user = User::create([
            'branch_id' => $branch->id,
            'restaurant_id' => 'REST-API-MONITOR',
            'name' => 'API Monitor Restoranı',
            'email' => 'api-monitor-restaurant@example.test',
            'password' => 'restaurant-secret',
            'is_admin' => false,
        ]);
        $staff = StaffProfile::create([
            'branch_id' => $branch->id,
            'name' => 'API Monitor Garsonu',
            'role' => 'Garson',
            'pin_code' => 'migrated',
            'pin_hash' => bcrypt('1234'),
            'pin_length' => 4,
            'avatar_color' => 'indigo',
            'is_active' => true,
        ]);

        return [$branch, $user, $staff];
    }

    private function trafficLog(Branch $branch, int $status, string $path): ApiTrafficLog
    {
        return ApiTrafficLog::create([
            'request_id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'restaurant_id' => 'REST-API-MONITOR',
            'method' => 'GET',
            'path' => $path,
            'route_name' => 'api.waiter.test',
            'status_code' => $status,
            'duration_ms' => 25,
            'request_size' => 0,
            'response_size' => 128,
            'occurred_at' => now(),
        ]);
    }
}
