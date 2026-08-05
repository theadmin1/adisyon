<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_restaurant_credentials_push_only_to_their_own_branch(): void
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

        $syncUuid = (string) Str::uuid();
        $response = $this->postJson('/api/v1/sync/push/restaurant', [
            'restaurant_id' => 'KADIKOY-001',
            'password' => 'kadikoy-secret',
            'batch_id' => 'offline-test-batch',
            'categories' => [[
                'sync_uuid' => $syncUuid,
                'name' => 'Offline Kategori',
                'slug' => 'offline-kategori',
                'sort_order' => 10,
                'is_active' => true,
            ]],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('synced_count', 1)
            ->assertJsonPath('synced_uuids.0', $syncUuid);

        $this->assertDatabaseHas('categories', [
            'branch_id' => $kadikoy->id,
            'sync_uuid' => $syncUuid,
            'name' => 'Offline Kategori',
        ]);
        $this->assertDatabaseMissing('categories', [
            'branch_id' => $other->id,
            'sync_uuid' => $syncUuid,
        ]);
    }
}
