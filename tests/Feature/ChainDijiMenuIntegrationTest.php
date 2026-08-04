<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DijiMenuIntegration;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChainDijiMenuIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_owner_can_configure_isolated_diji_menu_links(): void
    {
        [$organization, $branch, $owner] = $this->identity('DIJI');

        $this->actingAs($owner)->get(route('chain.diji-menu.index'))
            ->assertOk()
            ->assertSee('Diji Menü Yönetimi')
            ->assertSee($branch->name);

        $this->actingAs($owner)->put(route('chain.diji-menu.update'), [
            'base_url' => 'https://menu.example.test',
            'admin_path' => '/menu-management',
            'company_slug' => 'ornek-zincir',
            'branch_slugs' => [(string) $branch->id => 'merkez-sube'],
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $integration = DijiMenuIntegration::where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame(rtrim(url('/'), '/'), $integration->base_url);
        $this->assertSame('merkez-sube', $integration->branch_slugs[(string) $branch->id]);
        $this->assertSame(
            route('diji-menu.public', ['companySlug' => 'ornek-zincir', 'branchSlug' => 'merkez-sube']),
            $integration->publicMenuUrl($branch),
        );

        $category = Category::create(['branch_id' => $branch->id, 'name' => 'İçecekler', 'slug' => 'icecekler', 'is_active' => true]);
        Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Ev Yapımı Limonata',
            'slug' => 'ev-yapimi-limonata',
            'price' => 85,
            'is_active' => true,
        ]);

        $this->get($integration->publicMenuUrl($branch))
            ->assertOk()
            ->assertSee($branch->name)
            ->assertSee('Ev Yapımı Limonata')
            ->assertSee('₺85,00');
    }

    public function test_analyst_cannot_change_diji_menu_configuration(): void
    {
        [$organization, $branch] = $this->identity('DIJI-ANALYST');
        $analyst = User::factory()->create([
            'organization_id' => $organization->id,
            'chain_role' => 'analyst',
            'branch_id' => null,
        ]);

        $this->actingAs($analyst)->put(route('chain.diji-menu.update'), [
            'base_url' => 'https://malicious.example.test',
            'admin_path' => '/menu-management',
            'company_slug' => 'malicious',
            'branch_slugs' => [(string) $branch->id => 'branch'],
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseCount('diji_menu_integrations', 0);
    }

    /** @return array{Organization, Branch, User} */
    private function identity(string $suffix): array
    {
        $organization = Organization::create([
            'name' => "Diji Menü {$suffix}",
            'code' => 'DM-'.fake()->unique()->numberBetween(1000, 9999),
        ]);
        $branch = Branch::create([
            'name' => "Merkez {$suffix}",
            'code' => 'MRK-'.fake()->unique()->numberBetween(1000, 9999),
        ]);
        $organization->branches()->attach($branch);
        $owner = User::factory()->create([
            'organization_id' => $organization->id,
            'chain_role' => 'owner',
            'branch_id' => null,
        ]);

        return [$organization, $branch, $owner];
    }
}
