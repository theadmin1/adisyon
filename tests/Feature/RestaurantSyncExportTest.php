<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantSyncExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_credentials_export_only_their_own_branch(): void
    {
        $kadikoy = Branch::create([
            'name' => 'Kadıköy',
            'code' => 'KADIKOY-001',
            'is_active' => true,
        ]);
        $other = Branch::create([
            'name' => 'Merkez',
            'code' => 'MERKEZ-01',
            'is_active' => true,
        ]);

        User::factory()->create([
            'branch_id' => $kadikoy->id,
            'restaurant_id' => 'KADIKOY-001',
            'password' => 'kadikoy-secret',
            'is_admin' => false,
        ]);
        User::factory()->create([
            'branch_id' => $other->id,
            'restaurant_id' => 'MERKEZ-01',
            'password' => 'merkez-secret',
            'is_admin' => false,
        ]);

        $response = $this->postJson('/api/v1/sync/pull/restaurant', [
            'restaurant_id' => 'KADIKOY-001',
            'password' => 'kadikoy-secret',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.branch.id', $kadikoy->id)
            ->assertJsonPath('data.branch.code', 'KADIKOY-001')
            ->assertJsonCount(1, 'data.users')
            ->assertJsonPath('data.users.0.restaurant_id', 'KADIKOY-001');
    }

    public function test_restaurant_export_rejects_an_invalid_password(): void
    {
        $branch = Branch::create([
            'name' => 'Kadıköy',
            'code' => 'KADIKOY-001',
            'is_active' => true,
        ]);
        User::factory()->create([
            'branch_id' => $branch->id,
            'restaurant_id' => 'KADIKOY-001',
            'password' => 'correct-secret',
            'is_admin' => false,
        ]);

        $this->postJson('/api/v1/sync/pull/restaurant', [
            'restaurant_id' => 'KADIKOY-001',
            'password' => 'wrong-secret',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }
}
