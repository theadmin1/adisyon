<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminBranchCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creating_a_branch_also_creates_working_restaurant_credentials(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.branches.store'), [
            'name' => 'Kadıköy Restoranı',
            'code' => 'kadikoy-01',
            'contact_email' => 'yetkili@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('restaurant_credentials.restaurant_id', 'KADIKOY-01');

        $credentials = session('restaurant_credentials');
        $branch = Branch::where('code', 'KADIKOY-01')->firstOrFail();
        $restaurantUser = User::where('restaurant_id', 'KADIKOY-01')->firstOrFail();

        $this->assertSame($branch->id, $restaurantUser->branch_id);
        $this->assertFalse($restaurantUser->is_admin);
        $this->assertTrue(Hash::check($credentials['password'], $restaurantUser->password));

        $this->post(route('admin.logout'))->assertRedirect();

        $this->post(route('login.store'), [
            'restaurant_id' => 'KADIKOY-01',
            'password' => $credentials['password'],
        ])->assertRedirect(route('dashboard'));
    }

    public function test_branch_creation_rejects_an_existing_restaurant_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['restaurant_id' => 'EXISTING-01']);

        $this->actingAs($admin)->post(route('admin.branches.store'), [
            'name' => 'Çakışan Restoran',
            'code' => 'existing-01',
        ])->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('branches', ['code' => 'EXISTING-01']);
    }

    public function test_admin_can_create_a_branch_account_and_license_together(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.branches.store'), [
            'name' => 'Lisanslı Restoran',
            'code' => 'LICENSED-01',
            'create_license' => '1',
            'license_expires_at' => now()->addYear()->format('Y-m-d'),
            'license_max_devices' => 7,
            'license_notes' => 'Yıllık lisans',
        ]);

        $response->assertRedirect();
        $credentials = session('restaurant_credentials');
        $branch = Branch::where('code', 'LICENSED-01')->firstOrFail();
        $license = License::where('branch_id', $branch->id)->firstOrFail();

        $this->assertSame($credentials['license_key'], $license->license_key);
        $this->assertSame('Active', $license->status);
        $this->assertSame(7, $license->max_devices);
        $this->assertSame('Yıllık lisans', $license->notes);
    }

    public function test_admin_can_update_toggle_and_reset_a_branch_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $branch = Branch::create(['name' => 'Eski Ad', 'code' => 'OLD-01']);
        $restaurantUser = User::factory()->create([
            'branch_id' => $branch->id,
            'restaurant_id' => $branch->code,
            'is_admin' => false,
            'password' => 'old-password',
        ]);

        $this->actingAs($admin)->put(route('admin.branches.update', $branch), [
            'name' => 'Yeni Ad',
            'code' => 'new-01',
            'contact_email' => 'new@example.com',
            'phone' => '5551234567',
            'address' => 'Yeni adres',
        ])->assertRedirect();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Yeni Ad', 'code' => 'NEW-01']);
        $this->assertDatabaseHas('users', ['id' => $restaurantUser->id, 'restaurant_id' => 'NEW-01']);

        $this->post(route('admin.branches.toggle', $branch))->assertRedirect();
        $this->assertFalse($branch->fresh()->is_active);

        $oldHash = $restaurantUser->fresh()->password;
        $this->post(route('admin.branches.reset-password', $branch))->assertRedirect();
        $credentials = session('restaurant_credentials');

        $this->assertNotSame($oldHash, $restaurantUser->fresh()->password);
        $this->assertTrue(Hash::check($credentials['password'], $restaurantUser->fresh()->password));
    }

    public function test_admin_can_delete_a_branch_and_its_restaurant_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $branch = Branch::create(['name' => 'Silinecek', 'code' => 'DELETE-01']);
        $restaurantUser = User::factory()->create([
            'branch_id' => $branch->id,
            'restaurant_id' => $branch->code,
            'is_admin' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.branches.destroy', $branch))
            ->assertRedirect();

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
        $this->assertDatabaseMissing('users', ['id' => $restaurantUser->id]);
    }
}
