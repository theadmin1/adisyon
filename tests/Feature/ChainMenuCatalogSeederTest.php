<?php

namespace Tests\Feature;

use App\Models\ChainMenuCategory;
use App\Models\ChainMenuProduct;
use App\Models\Organization;
use Database\Seeders\ChainMenuCatalogSeeder;
use Database\Seeders\FbStockCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChainMenuCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeder_creates_ten_products_per_category_and_is_idempotent(): void
    {
        $organization = Organization::create([
            'name' => 'Antigravity Restoranları',
            'code' => 'ANTIGRAVITY',
            'is_active' => true,
        ]);

        $seeder = app(ChainMenuCatalogSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertSame(12, ChainMenuCategory::where('organization_id', $organization->id)->count());
        $this->assertSame(120, ChainMenuProduct::where('organization_id', $organization->id)->count());
        $this->assertFalse(ChainMenuCategory::where('organization_id', $organization->id)->where('slug', 'balik-deniz-urunleri')->exists());

        ChainMenuCategory::where('organization_id', $organization->id)->each(function (ChainMenuCategory $category): void {
            $this->assertSame(10, $category->products()->count(), $category->name.' kategorisi 10 ürün içermeli.');
        });

        ChainMenuProduct::where('organization_id', $organization->id)->each(function (ChainMenuProduct $product): void {
            $this->assertFileExists(public_path($product->image_path));
        });
    }

    public function test_fb_stock_seeder_creates_unit_based_raw_materials_and_is_idempotent(): void
    {
        $organization = Organization::create([
            'name' => 'Antigravity Restoranları',
            'code' => 'ANTIGRAVITY',
            'is_active' => true,
        ]);
        ChainMenuCategory::create([
            'organization_id' => $organization->id,
            'name' => 'F&B Stok Deposu',
            'slug' => 'fb-stok-deposu',
        ]);

        $seeder = app(FbStockCatalogSeeder::class);
        $seeder->run();
        $seeder->run();

        $category = ChainMenuCategory::where('organization_id', $organization->id)->where('slug', 'fb-stok-deposu')->firstOrFail();
        $this->assertSame(69, $category->products()->count());
        $this->assertDatabaseHas('chain_menu_products', [
            'organization_id' => $organization->id,
            'name' => 'Dana Kıyma',
            'unit' => 'kg',
            'item_type' => 'raw_material',
            'track_stock' => true,
        ]);
        $this->assertDatabaseHas('chain_menu_products', [
            'organization_id' => $organization->id,
            'name' => 'Ayçiçek Yağı',
            'unit' => 'l',
            'item_type' => 'raw_material',
            'image_path' => 'assets/images/fb-stock/products/fb-018.webp',
        ]);
        $this->assertDatabaseHas('chain_menu_products', ['organization_id' => $organization->id, 'name' => 'İçme Suyu', 'unit' => 'l']);
        $this->assertDatabaseHas('chain_menu_products', ['organization_id' => $organization->id, 'name' => 'Pul Biber', 'unit' => 'kg']);

        $category->products()->each(fn (ChainMenuProduct $product) => $this->assertFileExists(public_path($product->image_path)));
    }
}
