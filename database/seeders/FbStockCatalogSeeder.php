<?php

namespace Database\Seeders;

use App\Models\ChainMenuCategory;
use App\Models\ChainMenuProduct;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FbStockCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()
            ->where('code', env('FB_STOCK_ORGANIZATION_CODE', 'ALTF4TEKNOLOJI'))
            ->firstOrFail();

        DB::transaction(function () use ($organization): void {
            $category = $organization->menuCategories()
                ->where(function ($query): void {
                    $query->where('name', 'F&B Stok Deposu')
                        ->orWhereIn('slug', ['fb-stok-deposu', 'f-b-stok-deposu']);
                })
                ->first();

            if (! $category) {
                $category = ChainMenuCategory::create([
                    'organization_id' => $organization->id,
                    'name' => 'F&B Stok Deposu',
                    'slug' => 'fb-stok-deposu',
                    'sort_order' => ($organization->menuCategories()->max('sort_order') ?? 0) + 1,
                    'is_active' => true,
                ]);
            }

            foreach ($this->products() as $index => [$name, $unit, $group]) {
                ChainMenuProduct::updateOrCreate(
                    ['organization_id' => $organization->id, 'sku' => sprintf('FB-%03d', $index + 1)],
                    [
                        'chain_menu_category_id' => $category->id,
                        'name' => $name,
                        'base_price' => 0,
                        'unit' => $unit,
                        'item_type' => 'raw_material',
                        'track_stock' => true,
                        'discounted_price' => null,
                        'kitchen_department' => 'F&B Stok Deposu',
                        'description' => $group.' hammaddesi. Birim maliyeti ve şube atamalarını ihtiyaca göre güncelleyin.',
                        'image_path' => $this->imageForProduct($index + 1, $group),
                        'is_active' => true,
                    ],
                );
            }
        });
    }

    private function imageForProduct(int $number, string $group): string
    {
        if ($number <= 40) {
            return sprintf('assets/images/fb-stock/products/fb-%03d.webp', $number);
        }

        return $this->imageForGroup($group);
    }

    private function imageForGroup(string $group): string
    {
        return 'assets/images/fb-stock/'.match ($group) {
            'Tahıl ve bakliyat', 'Kuru gıda' => 'grains-dry-goods.webp',
            'Yağ' => 'oils-fats.webp',
            'Et ve tavuk' => 'meat-poultry.webp',
            'Balık ve deniz ürünü' => 'seafood.webp',
            'Süt ürünü', 'Kahvaltılık' => 'dairy-eggs.webp',
            'Sebze ve meyve' => 'vegetables-herbs.webp',
            'Sos ve yardımcı ürün' => 'sauces-condiments.webp',
            'Baharat' => 'spices.webp',
        };
    }

    private function products(): array
    {
        return [
            ['Baldo Pirinç', 'kg', 'Tahıl ve bakliyat'],
            ['Osmancık Pirinç', 'kg', 'Tahıl ve bakliyat'],
            ['Pilavlık Bulgur', 'kg', 'Tahıl ve bakliyat'],
            ['Köftelik İnce Bulgur', 'kg', 'Tahıl ve bakliyat'],
            ['Buğday', 'kg', 'Tahıl ve bakliyat'],
            ['Mısır', 'kg', 'Tahıl ve bakliyat'],
            ['Kırmızı Mercimek', 'kg', 'Tahıl ve bakliyat'],
            ['Yeşil Mercimek', 'kg', 'Tahıl ve bakliyat'],
            ['Nohut', 'kg', 'Tahıl ve bakliyat'],
            ['Kuru Fasulye', 'kg', 'Tahıl ve bakliyat'],
            ['Toz Şeker', 'kg', 'Kuru gıda'],
            ['Buğday Unu', 'kg', 'Kuru gıda'],
            ['Mısır Unu', 'kg', 'Kuru gıda'],
            ['İrmik', 'kg', 'Kuru gıda'],
            ['Nişasta', 'kg', 'Kuru gıda'],
            ['Makarna', 'kg', 'Kuru gıda'],
            ['Galeta Unu', 'kg', 'Kuru gıda'],
            ['Ayçiçek Yağı', 'l', 'Yağ'],
            ['Zeytinyağı', 'l', 'Yağ'],
            ['Mısırözü Yağı', 'l', 'Yağ'],
            ['Tereyağı', 'kg', 'Yağ'],
            ['Margarin', 'kg', 'Yağ'],
            ['Dana Kıyma', 'kg', 'Et ve tavuk'],
            ['Dana Kuşbaşı', 'kg', 'Et ve tavuk'],
            ['Dana Kontrfile', 'kg', 'Et ve tavuk'],
            ['Dana Antrikot', 'kg', 'Et ve tavuk'],
            ['Kuzu Kuşbaşı', 'kg', 'Et ve tavuk'],
            ['Kuzu Kıyma', 'kg', 'Et ve tavuk'],
            ['Kuzu Pirzola', 'kg', 'Et ve tavuk'],
            ['Tavuk Göğüs', 'kg', 'Et ve tavuk'],
            ['Tavuk But', 'kg', 'Et ve tavuk'],
            ['Tavuk Kanat', 'kg', 'Et ve tavuk'],
            ['Hindi Göğüs', 'kg', 'Et ve tavuk'],
            ['Balık Fileto', 'kg', 'Balık ve deniz ürünü'],
            ['Karides', 'kg', 'Balık ve deniz ürünü'],
            ['Süt', 'l', 'Süt ürünü'],
            ['Krema', 'l', 'Süt ürünü'],
            ['Yoğurt', 'kg', 'Süt ürünü'],
            ['Kaşar Peyniri', 'kg', 'Süt ürünü'],
            ['Beyaz Peynir', 'kg', 'Süt ürünü'],
            ['Labne', 'kg', 'Süt ürünü'],
            ['Yumurta', 'adet', 'Kahvaltılık'],
            ['Patates', 'kg', 'Sebze ve meyve'],
            ['Kuru Soğan', 'kg', 'Sebze ve meyve'],
            ['Sarımsak', 'kg', 'Sebze ve meyve'],
            ['Domates', 'kg', 'Sebze ve meyve'],
            ['Salatalık', 'kg', 'Sebze ve meyve'],
            ['Patlıcan', 'kg', 'Sebze ve meyve'],
            ['Kabak', 'kg', 'Sebze ve meyve'],
            ['Havuç', 'kg', 'Sebze ve meyve'],
            ['Yeşil Biber', 'kg', 'Sebze ve meyve'],
            ['Kırmızı Kapya Biber', 'kg', 'Sebze ve meyve'],
            ['Mantar', 'kg', 'Sebze ve meyve'],
            ['Limon', 'kg', 'Sebze ve meyve'],
            ['Maydanoz', 'kg', 'Sebze ve meyve'],
            ['Domates Salçası', 'kg', 'Sos ve yardımcı ürün'],
            ['Biber Salçası', 'kg', 'Sos ve yardımcı ürün'],
            ['Mayonez', 'kg', 'Sos ve yardımcı ürün'],
            ['Ketçap', 'kg', 'Sos ve yardımcı ürün'],
            ['Sirke', 'l', 'Sos ve yardımcı ürün'],
            ['Soya Sosu', 'l', 'Sos ve yardımcı ürün'],
            ['Tuz', 'kg', 'Baharat'],
            ['Karabiber', 'kg', 'Baharat'],
            ['Kırmızı Toz Biber', 'kg', 'Baharat'],
            ['Kimyon', 'kg', 'Baharat'],
            ['Kekik', 'kg', 'Baharat'],
            ['Nane', 'kg', 'Baharat'],
            ['İçme Suyu', 'l', 'Sos ve yardımcı ürün'],
            ['Pul Biber', 'kg', 'Baharat'],
        ];
    }
}
