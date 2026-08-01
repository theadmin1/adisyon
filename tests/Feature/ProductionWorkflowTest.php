<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductionRecipe;
use App\Models\ProductionWorkflow;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_scales_grams_to_kilograms_and_completes_stock_once(): void
    {
        [$branch, $user, $chef, $output, $beans, $meat, $spice] = $this->restaurantFixture('A');

        $this->actingAsStaff($user, $chef)->post(route('workflows.recipes.store'), [
            'name' => 'Kuru Fasulye',
            'output_product_id' => $output->id,
            'base_servings' => 10,
            'items' => [
                ['product_id' => $beans->id, 'quantity' => 300, 'unit' => 'g'],
                ['product_id' => $meat->id, 'quantity' => 300, 'unit' => 'g'],
                ['product_id' => $spice->id, 'quantity' => 30, 'unit' => 'g'],
            ],
        ])->assertRedirect();

        $recipe = ProductionRecipe::with('items')->firstOrFail();
        $this->assertCount(3, $recipe->items);

        $this->actingAsStaff($user, $chef)->post(route('workflows.store'), [
            'production_recipe_id' => $recipe->id,
            'planned_servings' => 100,
            'scheduled_for' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $workflow = ProductionWorkflow::firstOrFail();
        $this->actingAsStaff($user, $chef)->get(route('workflows.index'))
            ->assertOk()
            ->assertSee('Üretim İş Akışı')
            ->assertSee('3,000 kg')
            ->assertSee('0,300 kg');

        $this->actingAsStaff($user, $chef)->post(route('workflows.complete', $workflow))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(97.00, (float) $beans->fresh()->stock_quantity);
        $this->assertEquals(97.00, (float) $meat->fresh()->stock_quantity);
        $this->assertEquals(49.70, (float) $spice->fresh()->stock_quantity);
        $this->assertEquals(100.00, (float) $output->fresh()->stock_quantity);
        $this->assertSame('completed', $workflow->fresh()->status);
        $this->assertDatabaseCount('production_workflow_items', 3);
        $this->assertDatabaseCount('stock_movements', 4);

        $this->actingAsStaff($user, $chef)->post(route('workflows.complete', $workflow))->assertSessionHasErrors('workflow');
        $this->assertEquals(97.00, (float) $beans->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 4);
    }

    public function test_insufficient_ingredient_rolls_back_every_stock_change(): void
    {
        [$branch, $user, $chef, $output, $beans, $meat, $spice] = $this->restaurantFixture('B');
        $beans->update(['stock_quantity' => 1]);
        $recipe = ProductionRecipe::create(['branch_id' => $branch->id, 'output_product_id' => $output->id, 'created_by_user_id' => $user->id, 'name' => 'Toplu Yemek', 'base_servings' => 10, 'is_active' => true]);
        $recipe->items()->createMany([
            ['ingredient_product_id' => $beans->id, 'quantity' => 300, 'unit' => 'g'],
            ['ingredient_product_id' => $meat->id, 'quantity' => 300, 'unit' => 'g'],
        ]);
        $workflow = ProductionWorkflow::create(['branch_id' => $branch->id, 'production_recipe_id' => $recipe->id, 'created_by_user_id' => $user->id, 'workflow_number' => 'IA-ROLLBACK', 'recipe_name' => $recipe->name, 'planned_servings' => 100, 'status' => 'planned']);

        $this->actingAsStaff($user, $chef)->post(route('workflows.complete', $workflow))->assertSessionHasErrors('stock');

        $this->assertEquals(1.00, (float) $beans->fresh()->stock_quantity);
        $this->assertEquals(100.00, (float) $meat->fresh()->stock_quantity);
        $this->assertEquals(0.00, (float) $output->fresh()->stock_quantity);
        $this->assertSame('planned', $workflow->fresh()->status);
        $this->assertDatabaseCount('production_workflow_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_chain_panel_only_shows_and_manages_accessible_branch_workflows(): void
    {
        [$branchA, $restaurantUser, $chef, $output, $beans] = $this->restaurantFixture('C');
        [$branchB] = $this->restaurantFixture('D');
        $organization = Organization::create(['name' => 'Aşevi Zinciri', 'code' => 'ASEVI', 'is_active' => true]);
        $organization->branches()->attach([$branchA->id, $branchB->id]);
        $chainUser = User::factory()->create(['organization_id' => $organization->id, 'branch_id' => null, 'chain_role' => 'regional_manager', 'is_admin' => false]);
        $chainUser->chainBranches()->attach($branchA);
        $recipe = ProductionRecipe::create(['branch_id' => $branchA->id, 'output_product_id' => $output->id, 'created_by_user_id' => $restaurantUser->id, 'name' => 'Erişilebilir Reçete', 'base_servings' => 10, 'is_active' => true]);
        $recipe->items()->create(['ingredient_product_id' => $beans->id, 'quantity' => 300, 'unit' => 'g']);

        $this->actingAs($chainUser)->get(route('chain.workflows.index'))
            ->assertOk()
            ->assertSee('Erişilebilir Reçete')
            ->assertSee($branchA->name)
            ->assertDontSee($branchB->name);

        $this->actingAs($chainUser)->post(route('chain.workflows.store'), ['production_recipe_id' => $recipe->id, 'planned_servings' => 100])->assertRedirect();
        $this->assertDatabaseHas('production_workflows', ['branch_id' => $branchA->id, 'planned_servings' => 100, 'status' => 'planned']);
    }

    private function restaurantFixture(string $suffix): array
    {
        $branch = Branch::create(['name' => "Üretim Şubesi {$suffix}", 'code' => "WF-{$suffix}", 'is_active' => true]);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_admin' => false, 'organization_id' => null, 'chain_role' => null]);
        $chef = StaffProfile::create(['branch_id' => $branch->id, 'name' => "Şef {$suffix}", 'role' => 'Şef', 'pin_code' => 'migrated', 'pin_hash' => bcrypt('1234'), 'pin_length' => 4, 'avatar_color' => 'violet', 'is_active' => true]);
        $category = Category::create(['branch_id' => $branch->id, 'name' => "Üretim {$suffix}", 'slug' => 'uretim-'.Str::lower($suffix).'-'.Str::lower(Str::random(4)), 'is_active' => true]);
        $output = $this->product($branch, $category, "Kuru Fasulye {$suffix}", 0, 'porsiyon');
        $beans = $this->product($branch, $category, "Kuru Fasulye Hammaddesi {$suffix}", 100, 'kg');
        $meat = $this->product($branch, $category, "Et {$suffix}", 100, 'kg');
        $spice = $this->product($branch, $category, "Baharat {$suffix}", 50, 'kg');

        return [$branch, $user, $chef, $output, $beans, $meat, $spice];
    }

    private function product(Branch $branch, Category $category, string $name, float $stock, string $unit): Product
    {
        return Product::create(['branch_id' => $branch->id, 'category_id' => $category->id, 'name' => $name, 'slug' => Str::slug($name), 'sku' => Str::upper(Str::random(8)), 'price' => 10, 'stock_quantity' => $stock, 'min_stock_level' => 0, 'unit' => $unit, 'track_stock' => true, 'is_active' => true]);
    }

    private function actingAsStaff(User $user, StaffProfile $staff): static
    {
        return $this->actingAs($user)->withSession(['active_staff_id' => $staff->id, 'active_staff_name' => $staff->name, 'active_staff_role' => $staff->role, 'active_staff_color' => $staff->avatar_color]);
    }
}
