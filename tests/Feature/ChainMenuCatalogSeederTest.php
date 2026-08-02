<?php

namespace Tests\Feature;

use App\Models\ChainMenuCategory;
use App\Models\ChainMenuProduct;
use App\Models\Organization;
use Database\Seeders\ChainMenuCatalogSeeder;
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
}
