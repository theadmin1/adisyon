<?php

namespace Database\Seeders;

use App\Models\ChainMenuCategory;
use App\Models\ChainMenuProduct;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChainMenuCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()
            ->where('code', env('CHAIN_MENU_ORGANIZATION_CODE', 'ALTF4TEKNOLOJI'))
            ->firstOrFail();

        DB::transaction(function () use ($organization): void {
            $organization->menuCategories()->where('name', 'Çorba')->update(['sort_order' => 1]);

            foreach ($this->catalog() as $sortOrder => $definition) {
                $category = ChainMenuCategory::updateOrCreate(
                    ['organization_id' => $organization->id, 'slug' => $definition['slug']],
                    [
                        'name' => $definition['name'],
                        'sort_order' => $sortOrder + 2,
                        'is_active' => true,
                    ],
                );

                foreach ($definition['products'] as $index => [$name, $slug, $price]) {
                    ChainMenuProduct::updateOrCreate(
                        ['organization_id' => $organization->id, 'sku' => sprintf('%s-%03d', $definition['sku'], $index + 1)],
                        [
                            'chain_menu_category_id' => $category->id,
                            'name' => $name,
                            'base_price' => $price,
                            'discounted_price' => null,
                            'kitchen_department' => $definition['department'],
                            'description' => $name.' için özenle hazırlanan restoran porsiyonu.',
                            'image_path' => sprintf('assets/images/menu/%s/%s.webp', $definition['directory'], $slug),
                            'is_active' => true,
                        ],
                    );
                }
            }
        });
    }

    private function catalog(): array
    {
        return [
            [
                'name' => 'Başlangıçlar ve Mezeler', 'slug' => 'baslangiclar-mezeler', 'sku' => 'MEZ',
                'department' => 'Soğuk Mutfak', 'directory' => 'mezeler',
                'products' => [
                    ['Humus', 'humus', 120], ['Haydari', 'haydari', 110], ['Acılı Ezme', 'acili-ezme', 105],
                    ['Şakşuka', 'saksuka', 125], ['Patlıcan Salatası', 'patlican-salatasi', 125], ['Atom Meze', 'atom-meze', 135],
                    ['Fava', 'fava', 120], ['Muhammara', 'muhammara', 135], ['Zeytinyağlı Yaprak Sarma', 'zeytinyagli-yaprak-sarma', 145],
                    ['İçli Köfte', 'icli-kofte', 95],
                ],
            ],
            [
                'name' => 'Salatalar', 'slug' => 'salatalar', 'sku' => 'SAL',
                'department' => 'Soğuk Mutfak', 'directory' => 'salatalar',
                'products' => [
                    ['Çoban Salata', 'coban-salata', 140], ['Mevsim Salata', 'mevsim-salata', 135], ['Gavurdağı Salatası', 'gavurdagi-salatasi', 165],
                    ['Tavuklu Sezar Salata', 'tavuklu-sezar-salata', 245], ['Akdeniz Salatası', 'akdeniz-salatasi', 175], ['Roka Parmesan Salatası', 'roka-parmesan-salatasi', 195],
                    ['Ton Balıklı Salata', 'ton-balikli-salata', 235], ['Hellim Peynirli Salata', 'hellim-peynirli-salata', 225], ['Piyaz', 'piyaz', 155],
                    ['Yoğurtlu Semizotu Salatası', 'yogurtlu-semizotu-salatasi', 145],
                ],
            ],
            [
                'name' => 'Ana Yemekler', 'slug' => 'ana-yemekler', 'sku' => 'ANA',
                'department' => 'Sıcak Mutfak', 'directory' => 'ana-yemekler',
                'products' => [
                    ['Kuru Fasulye', 'kuru-fasulye', 220], ['Etli Nohut', 'etli-nohut', 240], ['Tas Kebabı', 'tas-kebabi', 360],
                    ['Hünkar Beğendi', 'hunkar-begendi', 390], ['Karnıyarık', 'karniyarik', 280], ['İzmir Köfte', 'izmir-kofte', 310],
                    ['Et Sote', 'et-sote', 380], ['Tavuk Sote', 'tavuk-sote', 290], ['Mantı', 'manti', 260],
                    ['Patlıcan Musakka', 'patlican-musakka', 285],
                ],
            ],
            [
                'name' => 'Izgara ve Kebaplar', 'slug' => 'izgara-kebaplar', 'sku' => 'IZG',
                'department' => 'Izgara', 'directory' => 'izgara-kebaplar',
                'products' => [
                    ['Adana Kebap', 'adana-kebap', 360], ['Urfa Kebap', 'urfa-kebap', 360], ['Tavuk Şiş', 'tavuk-sis', 310],
                    ['Kuzu Şiş', 'kuzu-sis', 440], ['Izgara Köfte', 'izgara-kofte', 330], ['Kuzu Pirzola', 'kuzu-pirzola', 520],
                    ['Karışık Izgara', 'karisik-izgara', 590], ['Beyti Kebap', 'beyti-kebap', 390], ['Ali Nazik Kebap', 'ali-nazik-kebap', 420],
                    ['İskender Kebap', 'iskender-kebap', 410],
                ],
            ],
            [
                'name' => 'Pide ve Lahmacun', 'slug' => 'pide-lahmacun', 'sku' => 'PID',
                'department' => 'Fırın', 'directory' => 'pide-lahmacun',
                'products' => [
                    ['Kıymalı Pide', 'kiymali-pide', 260], ['Kuşbaşılı Pide', 'kusbasili-pide', 310], ['Kaşarlı Pide', 'kasarli-pide', 250],
                    ['Karışık Pide', 'karisik-pide', 320], ['Sucuklu Kaşarlı Pide', 'sucuklu-kasarli-pide', 295], ['Kavurmalı Pide', 'kavurmali-pide', 340],
                    ['Lahmacun', 'lahmacun', 115], ['Fındık Lahmacun', 'findik-lahmacun', 70], ['Etli Ekmek', 'etli-ekmek', 285],
                    ['Bıçak Arası', 'bicak-arasi', 330],
                ],
            ],
            [
                'name' => 'Pizza', 'slug' => 'pizza', 'sku' => 'PIZ',
                'department' => 'Fırın', 'directory' => 'pizza',
                'products' => [
                    ['Margherita Pizza', 'margherita-pizza', 260], ['Karışık Pizza', 'karisik-pizza', 340], ['Sucuklu Pizza', 'sucuklu-pizza', 315],
                    ['Dört Peynirli Pizza', 'dort-peynirli-pizza', 335], ['Mantarlı Pizza', 'mantarli-pizza', 285], ['Tavuklu Pizza', 'tavuklu-pizza', 320],
                    ['Sebzeli Pizza', 'sebzeli-pizza', 280], ['Pepperoni Pizza', 'pepperoni-pizza', 325], ['BBQ Tavuk Pizza', 'bbq-tavuk-pizza', 340],
                    ['Dört Mevsim Pizza', 'dort-mevsim-pizza', 350],
                ],
            ],
            [
                'name' => 'Burger ve Sandviç', 'slug' => 'burger-sandvic', 'sku' => 'BRG',
                'department' => 'Sıcak Mutfak', 'directory' => 'burger-sandvic',
                'products' => [
                    ['Cheeseburger', 'cheeseburger', 270], ['Double Cheeseburger', 'double-cheeseburger', 350], ['Crispy Chicken Burger', 'crispy-chicken-burger', 260],
                    ['Mantarlı Burger', 'mantarli-burger', 295], ['BBQ Burger', 'bbq-burger', 310], ['Chicken Burger', 'chicken-burger', 250],
                    ['Kumru Sandviç', 'kumru-sandvic', 220], ['Club Sandviç', 'club-sandvic', 240], ['Köfte Ekmek', 'kofte-ekmek', 210],
                    ['Tavuk Wrap', 'tavuk-wrap', 230],
                ],
            ],
            [
                'name' => 'Makarna', 'slug' => 'makarna', 'sku' => 'MAK',
                'department' => 'Sıcak Mutfak', 'directory' => 'makarna',
                'products' => [
                    ['Spaghetti Bolognese', 'spaghetti-bolognese', 260], ['Spaghetti Napoliten', 'spaghetti-napoliten', 210], ['Fettuccine Alfredo', 'fettuccine-alfredo', 255],
                    ['Penne Arrabbiata', 'penne-arrabbiata', 220], ['Penne Pesto', 'penne-pesto', 235], ['Mantarlı Fettuccine', 'mantarli-fettuccine', 250],
                    ['Tavuklu Makarna', 'tavuklu-makarna', 280], ['Sebzeli Makarna', 'sebzeli-makarna', 230], ['Lazanya', 'lazanya', 310],
                    ['Mac and Cheese', 'mac-and-cheese', 240],
                ],
            ],
            [
                'name' => 'Tatlılar', 'slug' => 'tatlilar', 'sku' => 'TAT',
                'department' => 'Pastane', 'directory' => 'tatlilar',
                'products' => [
                    ['Fıstıklı Baklava', 'fistikli-baklava', 190], ['Künefe', 'kunefe', 210], ['Fırın Sütlaç', 'firin-sutlac', 145],
                    ['Kazandibi', 'kazandibi', 145], ['Tavukgöğsü', 'tavukgogsu', 145], ['San Sebastian Cheesecake', 'san-sebastian-cheesecake', 220],
                    ['Profiterol', 'profiterol', 175], ['Trileçe', 'trilece', 165], ['Revani', 'revani', 140],
                    ['Dondurma Tabağı', 'dondurma-tabagi', 160],
                ],
            ],
            [
                'name' => 'Soğuk İçecekler', 'slug' => 'soguk-icecekler', 'sku' => 'SGI',
                'department' => 'Bar', 'directory' => 'soguk-icecekler',
                'products' => [
                    ['Kola', 'kola', 65], ['Gazoz', 'gazoz', 60], ['Portakallı Gazlı İçecek', 'portakalli-gazli-icecek', 65],
                    ['Ayran', 'ayran', 50], ['Şalgam Suyu', 'salgam-suyu', 55], ['Ev Yapımı Limonata', 'ev-yapimi-limonata', 85],
                    ['Taze Portakal Suyu', 'taze-portakal-suyu', 95], ['Su', 'su', 30], ['Maden Suyu', 'maden-suyu', 40],
                    ['Şeftalili Soğuk Çay', 'seftalili-soguk-cay', 70],
                ],
            ],
            [
                'name' => 'Sıcak İçecekler', 'slug' => 'sicak-icecekler', 'sku' => 'SCI',
                'department' => 'Bar', 'directory' => 'sicak-icecekler',
                'products' => [
                    ['Türk Kahvesi', 'turk-kahvesi', 75], ['Espresso', 'espresso', 70], ['Americano', 'americano', 85],
                    ['Latte', 'latte', 100], ['Cappuccino', 'cappuccino', 100], ['Demleme Çay', 'demleme-cay', 35],
                    ['Bitki Çayı', 'bitki-cayi', 75], ['Salep', 'salep', 95], ['Sıcak Çikolata', 'sicak-cikolata', 105],
                    ['Filtre Kahve', 'filtre-kahve', 90],
                ],
            ],
            [
                'name' => 'Kahvaltı', 'slug' => 'kahvalti', 'sku' => 'KAH',
                'department' => 'Kahvaltı', 'directory' => 'kahvalti',
                'products' => [
                    ['Serpme Kahvaltı', 'serpme-kahvalti', 650], ['Kahvaltı Tabağı', 'kahvalti-tabagi', 320], ['Menemen', 'menemen', 190],
                    ['Sucuklu Yumurta', 'sucuklu-yumurta', 220], ['Omlet', 'omlet', 170], ['Kuymak', 'kuymak', 230],
                    ['Peynirli Gözleme', 'peynirli-gozleme', 190], ['Simit Tabağı', 'simit-tabagi', 160], ['Sahanda Yumurta', 'sahanda-yumurta', 150],
                    ['Avokadolu Tost', 'avokadolu-tost', 240],
                ],
            ],
        ];
    }
}
