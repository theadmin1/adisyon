<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChainBranchTableEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_chain_manager_can_edit_an_existing_table(): void
    {
        [$branch, $manager] = $this->branchManagerFixture();
        $oldHall = Hall::withoutGlobalScopes()->create(['branch_id' => $branch->id, 'name' => 'İç Mekân', 'code' => 'IC', 'is_active' => true]);
        $newHall = Hall::withoutGlobalScopes()->create(['branch_id' => $branch->id, 'name' => 'Teras', 'code' => 'TERAS', 'is_active' => true]);
        $table = DiningTable::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'hall_id' => $oldHall->id,
            'name' => 'Masa 4',
            'code' => 'M4',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get(route('chain.branches.index'))
            ->assertOk()
            ->assertSee('Masayı Düzenle')
            ->assertSee(route('chain.branches.tables.update', [$branch, $table]), false);

        $this->actingAs($manager)->put(route('chain.branches.tables.update', [$branch, $table]), [
            'form_context' => "table_{$branch->id}_edit_{$table->id}",
            'hall_id' => $newHall->id,
            'name' => 'Teras 8',
            'code' => 'T8',
            'capacity' => 8,
            'notes' => 'Pencere yanı',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $table->refresh();
        $this->assertSame($newHall->id, $table->hall_id);
        $this->assertSame('Teras 8', $table->name);
        $this->assertSame('T8', $table->code);
        $this->assertSame(8, $table->capacity);
        $this->assertSame('Pencere yanı', $table->notes);
        $this->assertTrue($table->is_active);
        $this->assertSame('available', $table->status->value);
    }

    public function test_table_cannot_be_moved_to_another_branches_category(): void
    {
        [$branch, $manager] = $this->branchManagerFixture();
        $foreignBranch = Branch::create(['name' => 'Yabancı Şube', 'code' => 'YBN-EDIT']);
        $foreignHall = Hall::withoutGlobalScopes()->create(['branch_id' => $foreignBranch->id, 'name' => 'Yabancı Salon', 'code' => 'YBN', 'is_active' => true]);
        $table = DiningTable::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'name' => 'Masa 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->actingAs($manager)->put(route('chain.branches.tables.update', [$branch, $table]), [
            'hall_id' => $foreignHall->id,
            'name' => 'Masa 1',
            'capacity' => 4,
        ])->assertRedirect()->assertSessionHasErrors('hall_id');

        $this->assertNull($table->fresh()->hall_id);
    }

    public function test_analyst_cannot_edit_a_table(): void
    {
        [$branch] = $this->branchManagerFixture();
        $analyst = User::factory()->create([
            'organization_id' => $branch->organizations()->firstOrFail()->id,
            'chain_role' => 'analyst',
            'branch_id' => null,
        ]);
        $table = DiningTable::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'name' => 'Masa 2',
            'capacity' => 2,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->actingAs($analyst)->put(route('chain.branches.tables.update', [$branch, $table]), [
            'name' => 'Değişmemeli',
            'capacity' => 6,
        ])->assertForbidden();

        $this->assertSame('Masa 2', $table->fresh()->name);
    }

    /** @return array{Branch, User} */
    private function branchManagerFixture(): array
    {
        $organization = Organization::create(['name' => 'Masa Düzenleme Zinciri', 'code' => 'MASA-EDIT-'.fake()->unique()->numberBetween(100, 999)]);
        $branch = Branch::create(['name' => 'Düzenlenebilir Şube', 'code' => 'EDIT-'.fake()->unique()->numberBetween(100, 999)]);
        $organization->branches()->attach($branch);
        $manager = User::factory()->create([
            'organization_id' => $organization->id,
            'chain_role' => 'owner',
            'branch_id' => null,
        ]);

        return [$branch, $manager];
    }
}
