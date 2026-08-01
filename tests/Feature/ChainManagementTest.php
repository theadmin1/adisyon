<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Check;
use App\Models\ChainMenuCategory;
use App\Models\ChainMenuProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChainManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_user_can_log_in_and_see_only_organization_branches(): void
    {
        $organization = Organization::create(['name' => 'Demo Zincir', 'code' => 'DEMO']);
        $ownBranch = Branch::create(['name' => 'Kadıköy', 'code' => 'KDK-01', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Başka Zincir', 'code' => 'BSK-01', 'is_active' => true]);
        $organization->branches()->attach($ownBranch);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'chain_role' => 'owner',
            'branch_id' => null,
            'is_admin' => false,
        ]);

        $this->post(route('chain.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('chain.dashboard'));

        $this->get(route('chain.dashboard'))
            ->assertOk()
            ->assertSee('Kadıköy')
            ->assertDontSee('Başka Zincir');
    }

    public function test_branch_assignment_limits_a_regional_chain_user(): void
    {
        $organization = Organization::create(['name' => 'Demo Zincir', 'code' => 'DEMO']);
        $first = Branch::create(['name' => 'Avrupa Şubesi', 'code' => 'AVR-01']);
        $second = Branch::create(['name' => 'Anadolu Şubesi', 'code' => 'AND-01']);
        $organization->branches()->attach([$first->id, $second->id]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'chain_role' => 'regional_manager',
            'branch_id' => null,
        ]);
        $user->chainBranches()->attach($second);

        $this->actingAs($user)->get(route('chain.dashboard'))
            ->assertOk()
            ->assertSee('Anadolu Şubesi')
            ->assertDontSee('Avrupa Şubesi');
    }

    public function test_dashboard_totals_exclude_another_organization_sales(): void
    {
        $organization = Organization::create(['name' => 'Demo Zincir', 'code' => 'DEMO']);
        $ownBranch = Branch::create(['name' => 'Merkez', 'code' => 'MRK-01']);
        $otherBranch = Branch::create(['name' => 'Harici', 'code' => 'HRC-01']);
        $organization->branches()->attach($ownBranch);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'chain_role' => 'owner',
            'branch_id' => null,
        ]);

        Check::create([
            'branch_id' => $ownBranch->id,
            'check_number' => 'OWN-1',
            'status' => 'closed',
            'total' => 125,
            'closed_at' => now(),
        ]);
        Check::create([
            'branch_id' => $otherBranch->id,
            'check_number' => 'OTHER-1',
            'status' => 'closed',
            'total' => 999,
            'closed_at' => now(),
        ]);

        $this->actingAs($user)->get(route('chain.dashboard'))
            ->assertOk()
            ->assertSee('₺125,00')
            ->assertDontSee('₺999,00');
    }

    public function test_restaurant_user_cannot_access_chain_dashboard(): void
    {
        $restaurantUser = User::factory()->create(['organization_id' => null, 'chain_role' => null]);

        $this->actingAs($restaurantUser)->get(route('chain.dashboard'))
            ->assertRedirect(route('chain.login'));
    }

    public function test_admin_can_create_chain_user_with_selected_branch_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organization = Organization::create(['name' => 'Yeni Zincir', 'code' => 'YENI']);
        $first = Branch::create(['name' => 'Birinci', 'code' => 'BIR-01']);
        $second = Branch::create(['name' => 'İkinci', 'code' => 'IKI-01']);
        $organization->branches()->attach([$first->id, $second->id]);

        $this->actingAs($admin)->post(route('admin.chain-users.store'), [
            'organization_id' => $organization->id,
            'name' => 'Bölge Müdürü',
            'email' => 'bolge@example.com',
            'password' => 'StrongPass!123',
            'chain_role' => 'regional_manager',
            'branch_ids' => [$second->id],
        ])->assertRedirect();

        $user = User::where('email', 'bolge@example.com')->firstOrFail();
        $this->assertSame([$second->id], $user->accessibleChainBranchIds());
    }

    public function test_admin_cannot_assign_another_chains_branch_to_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organization = Organization::create(['name' => 'Birinci Zincir', 'code' => 'BIR']);
        $foreignBranch = Branch::create(['name' => 'Yabancı Şube', 'code' => 'YBN-01']);

        $this->actingAs($admin)->post(route('admin.chain-users.store'), [
            'organization_id' => $organization->id,
            'name' => 'Yetkisiz Atama',
            'email' => 'invalid@example.com',
            'password' => 'StrongPass!123',
            'chain_role' => 'regional_manager',
            'branch_ids' => [$foreignBranch->id],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'invalid@example.com']);
    }

    public function test_chain_report_rejects_inaccessible_branch_filter(): void
    {
        $organization = Organization::create(['name' => 'Rapor Zinciri', 'code' => 'RAPOR']);
        $ownBranch = Branch::create(['name' => 'Yetkili', 'code' => 'YET-01']);
        $foreignBranch = Branch::create(['name' => 'Yetkisiz', 'code' => 'YZ-01']);
        $organization->branches()->attach($ownBranch);
        $user = User::factory()->create(['organization_id' => $organization->id, 'chain_role' => 'analyst', 'branch_id' => null]);

        $this->actingAs($user)->get(route('chain.reports.index', ['branch_id' => $foreignBranch->id]))
            ->assertForbidden();
    }

    public function test_chain_owner_can_publish_central_product_with_branch_price_override(): void
    {
        $organization = Organization::create(['name' => 'Menü Zinciri', 'code' => 'MENU']);
        $first = Branch::create(['name' => 'Merkez', 'code' => 'MENU-01']);
        $second = Branch::create(['name' => 'Sahil', 'code' => 'MENU-02']);
        $organization->branches()->attach([$first->id, $second->id]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'chain_role' => 'owner', 'branch_id' => null]);
        $category = ChainMenuCategory::create(['organization_id' => $organization->id, 'name' => 'Burger', 'slug' => 'burger']);

        $this->actingAs($owner)->post(route('chain.menu.products.store'), [
            'chain_menu_category_id' => $category->id,
            'name' => 'Merkez Burger',
            'sku' => 'BRG-100',
            'base_price' => 250,
            'branch_ids' => [$first->id, $second->id],
            'enabled_branch_ids' => [$first->id],
            'price_overrides' => [$second->id => 275],
            'is_active' => 1,
        ])->assertRedirect();

        $centralProduct = ChainMenuProduct::where('sku', 'BRG-100')->firstOrFail();
        $this->actingAs($owner)->post(route('chain.menu.products.publish', $centralProduct), [
            'branch_ids' => [$first->id, $second->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['branch_id' => $first->id, 'sku' => 'BRG-100', 'price' => 250, 'is_active' => true]);
        $this->assertDatabaseHas('products', ['branch_id' => $second->id, 'sku' => 'BRG-100', 'price' => 275, 'is_active' => false]);
        $this->assertSame(2, Category::withoutGlobalScopes()->where('slug', 'burger')->count());
    }

    public function test_analyst_cannot_change_central_menu(): void
    {
        $organization = Organization::create(['name' => 'Salt Okunur', 'code' => 'READ']);
        $analyst = User::factory()->create(['organization_id' => $organization->id, 'chain_role' => 'analyst', 'branch_id' => null]);

        $this->actingAs($analyst)->post(route('chain.menu.categories.store'), ['name' => 'Yetkisiz'])
            ->assertForbidden();
        $this->assertDatabaseMissing('chain_menu_categories', ['name' => 'Yetkisiz']);
    }

    public function test_chain_cannot_publish_another_organizations_product(): void
    {
        $firstOrganization = Organization::create(['name' => 'Birinci', 'code' => 'ORG1']);
        $secondOrganization = Organization::create(['name' => 'İkinci', 'code' => 'ORG2']);
        $owner = User::factory()->create(['organization_id' => $firstOrganization->id, 'chain_role' => 'owner', 'branch_id' => null]);
        $category = ChainMenuCategory::create(['organization_id' => $secondOrganization->id, 'name' => 'İçecek', 'slug' => 'icecek']);
        $product = ChainMenuProduct::create(['organization_id' => $secondOrganization->id, 'chain_menu_category_id' => $category->id, 'name' => 'Kola', 'sku' => 'DRK-1', 'base_price' => 50]);

        $this->actingAs($owner)->post(route('chain.menu.products.publish', $product), ['branch_ids' => [999]])
            ->assertNotFound();
    }
}
