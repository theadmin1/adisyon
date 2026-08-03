<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Check;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Product;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_loads_edit_data_on_demand_instead_of_duplicating_large_images(): void
    {
        [$branch, $user, $staff] = $this->identity('PRODUCT');
        $category = $this->category($branch);
        $image = 'data:image/png;base64,'.str_repeat('A', 12000);
        $product = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Görselli Ürün',
            'slug' => 'gorselli-urun',
            'sku' => 'GRS-1',
            'price' => 100,
            'image_path' => $image,
            'is_active' => true,
        ]);

        $response = $this->actingAsStaff($user, $staff)->get(route('products.index'));

        $response->assertOk()
            ->assertSee("editProduct({$product->id}, this)", false)
            ->assertSee('app-modal-panel', false);
        $this->assertSame(1, substr_count($response->getContent(), $image));

        $this->actingAsStaff($user, $staff)
            ->getJson(route('products.edit-data', $product))
            ->assertOk()
            ->assertJsonPath('id', $product->id)
            ->assertJsonPath('name', 'Görselli Ürün')
            ->assertJsonPath('image_path', $image);
    }

    public function test_product_can_be_updated_and_its_existing_image_removed(): void
    {
        [$branch, $user, $staff] = $this->identity('UPDATE');
        $category = $this->category($branch);
        $product = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Eski Ürün',
            'slug' => 'eski-urun',
            'sku' => 'ESK-1',
            'price' => 20,
            'image_path' => 'uploads/products/old.webp',
            'is_active' => true,
        ]);

        $this->actingAsStaff($user, $staff)->put(route('products.update', $product), [
            'form_context' => 'product_update',
            'product_id' => $product->id,
            'category_id' => $category->id,
            'name' => 'Yeni Ürün',
            'sku' => 'YNI-1',
            'price' => 35.50,
            'remove_image' => 1,
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('Yeni Ürün', $product->name);
        $this->assertSame('35.50', $product->price);
        $this->assertNull($product->image_path);
    }

    public function test_table_can_be_created_and_updated_without_a_code(): void
    {
        [$branch, $user, $staff] = $this->identity('TABLE');
        $hall = Hall::create(['branch_id' => $branch->id, 'name' => 'Bahçe', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAsStaff($user, $staff)->post(route('tables.store'), [
            'form_context' => 'table_create',
            'hall_id' => $hall->id,
            'name' => 'Masa 10',
            'capacity' => 4,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $table = DiningTable::firstOrFail();
        $this->assertNull($table->code);

        $this->actingAsStaff($user, $staff)->patch(route('tables.update', $table), [
            'form_context' => 'table_update_'.$table->id,
            'hall_id' => $hall->id,
            'name' => 'Masa 10 Güncel',
            'capacity' => 6,
            'status' => 'reserved',
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $table->refresh();
        $this->assertSame('Masa 10 Güncel', $table->name);
        $this->assertSame(6, $table->capacity);
        $this->assertSame('reserved', $table->status->value);
        $this->assertTrue($table->is_active);
    }

    public function test_table_with_an_open_check_cannot_be_deactivated(): void
    {
        [$branch, $user, $staff] = $this->identity('OPEN');
        $hall = Hall::create(['branch_id' => $branch->id, 'name' => 'Salon', 'sort_order' => 1, 'is_active' => true]);
        $table = DiningTable::create([
            'branch_id' => $branch->id,
            'hall_id' => $hall->id,
            'name' => 'Masa 1',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);
        Check::create([
            'branch_id' => $branch->id,
            'dining_table_id' => $table->id,
            'check_number' => 'CHK-OPEN-1',
            'status' => 'open',
            'subtotal' => 0,
            'total' => 0,
            'opened_at' => now(),
        ]);

        $this->actingAsStaff($user, $staff)->patch(route('tables.update', $table), [
            'form_context' => 'table_update_'.$table->id,
            'hall_id' => $hall->id,
            'name' => $table->name,
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => 0,
        ])->assertRedirect()->assertSessionHasErrors('table');

        $this->assertTrue($table->fresh()->is_active);
        $this->assertSame('occupied', $table->fresh()->status->value);
    }

    public function test_settings_table_management_renders_responsive_modals_and_generated_routes(): void
    {
        [$branch, $user, $staff] = $this->identity('SETTINGS');
        $hall = Hall::create(['branch_id' => $branch->id, 'name' => 'Teras', 'sort_order' => 1, 'is_active' => true]);
        DiningTable::create(['branch_id' => $branch->id, 'hall_id' => $hall->id, 'name' => 'T1', 'capacity' => 2, 'status' => 'available', 'is_active' => true]);

        $this->actingAsStaff($user, $staff)
            ->get(route('settings.index', ['tab' => 'tables']))
            ->assertOk()
            ->assertSee('Masa Kodu')
            ->assertSee('app-modal-panel', false)
            ->assertSee('hallUpdateUrlTemplate', false)
            ->assertSee('tableUpdateUrlTemplate', false);
    }

    /** @return array{Branch, User, StaffProfile} */
    private function identity(string $suffix): array
    {
        $branch = Branch::create(['name' => "Restoran {$suffix}", 'code' => "RST-{$suffix}", 'is_active' => true]);
        $user = User::create([
            'branch_id' => $branch->id,
            'name' => "Yönetici {$suffix}",
            'email' => strtolower($suffix).'@restaurant.test',
            'password' => 'secret-password',
            'is_admin' => false,
        ]);
        $staff = StaffProfile::create([
            'branch_id' => $branch->id,
            'name' => "Personel {$suffix}",
            'role' => 'Yönetici',
            'pin_code' => 'migrated',
            'pin_hash' => bcrypt('1234'),
            'pin_length' => 4,
            'avatar_color' => 'indigo',
            'is_active' => true,
        ]);

        return [$branch, $user, $staff];
    }

    private function category(Branch $branch): Category
    {
        return Category::create([
            'branch_id' => $branch->id,
            'name' => 'Ana Yemekler',
            'slug' => 'ana-yemekler',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function actingAsStaff(User $user, StaffProfile $staff): static
    {
        return $this->actingAs($user)->withSession([
            'active_staff_id' => $staff->id,
            'active_staff_name' => $staff->name,
            'active_staff_role' => $staff->role,
            'active_staff_color' => $staff->avatar_color,
        ]);
    }
}
