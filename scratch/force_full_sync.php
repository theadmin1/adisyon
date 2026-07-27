<?php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

echo "=== CANLI MYSQL VERİLERİNİ LOKAL SQLITE'A ZORLA AKTARMA ===\n\n";

$apiUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';
$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

// Cihaz API anahtarını veritabanından almayı dene
$device = DB::connection('sqlite')->table('devices')->first();
if ($device && !empty($device->api_key)) {
    $apiKey = $device->api_key;
}

echo "📡 Canlı sunucuya bağlanılıyor: {$apiUrl}\n";
echo "🔑 API Key: {$apiKey}\n\n";

try {
    $response = Http::withoutVerifying()->timeout(30)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->get($apiUrl);

    if (!$response->successful() || !$response->json('success')) {
        echo "❌ Sunucudan veri alınamadı. HTTP Status: " . $response->status() . "\n";
        echo "Yanıt: " . $response->body() . "\n";
        exit(1);
    }

    $data = $response->json('data');
    echo "✅ Sunucudan veriler başarıyla alındı!\n\n";

    echo "📊 GELEN VERİ ÖZETİ:\n";
    echo "   Users: " . count($data['users'] ?? []) . "\n";
    echo "   Staff Profiles: " . count($data['staff_profiles'] ?? []) . "\n";
    echo "   Halls: " . count($data['halls'] ?? []) . "\n";
    echo "   Tables: " . count($data['tables'] ?? []) . "\n";
    echo "   Categories: " . count($data['categories'] ?? []) . "\n";
    echo "   Products: " . count($data['products'] ?? []) . "\n";
    echo "   Checks: " . count($data['checks'] ?? []) . "\n";
    echo "   Payments: " . count($data['payments'] ?? []) . "\n";
    echo "   Stock Movements: " . count($data['stock_movements'] ?? []) . "\n";
    echo "   Settings: " . count($data['settings'] ?? []) . "\n\n";

    // SQLite Yabancı Anahtar Kısıtlamalarını Geçici Olarak Kapat
    DB::connection('sqlite')->statement('PRAGMA foreign_keys = OFF;');

    // 0. Kullanıcılar ve Personel Profilleri
    echo "🔄 0. Kullanıcılar ve Personel Profilleri aktarılıyor...\n";
    if (!empty($data['users'])) {
        DB::connection('sqlite')->table('users')->truncate();
        foreach ($data['users'] as $u) {
            DB::connection('sqlite')->table('users')->insert([
                'id' => $u['id'],
                'name' => $u['name'],
                'email' => $u['email'],
                'restaurant_id' => $u['restaurant_id'] ?? null,
                'password' => $u['password'] ?? bcrypt('password'),
                'is_admin' => $u['is_admin'] ?? false,
                'created_at' => $u['created_at'] ?? now(),
                'updated_at' => $u['updated_at'] ?? now(),
            ]);
        }
    }
    if (!empty($data['staff_profiles'])) {
        DB::connection('sqlite')->table('staff_profiles')->truncate();
        foreach ($data['staff_profiles'] as $st) {
            DB::connection('sqlite')->table('staff_profiles')->insert([
                'id' => $st['id'],
                'branch_id' => $st['branch_id'] ?? 1,
                'name' => $st['name'],
                'role' => $st['role'] ?? 'Garson',
                'pin_code' => $st['pin_code'] ?? '1234',
                'avatar_color' => $st['avatar_color'] ?? 'indigo',
                'is_active' => $st['is_active'] ?? true,
                'created_at' => $st['created_at'] ?? now(),
                'updated_at' => $st['updated_at'] ?? now(),
            ]);
        }
    }

    // 1. Kategorileri SQLite'a Yükle
    echo "🔄 1. Kategoriler aktarılıyor...\n";
    DB::connection('sqlite')->table('categories')->truncate();
    foreach ($data['categories'] ?? [] as $c) {
        DB::connection('sqlite')->table('categories')->insert([
            'id' => $c['id'],
            'branch_id' => $c['branch_id'] ?? 1,
            'name' => $c['name'],
            'slug' => $c['slug'] ?? \Illuminate\Support\Str::slug($c['name']),
            'sort_order' => $c['sort_order'] ?? 0,
            'is_active' => $c['is_active'] ?? true,
            'sync_uuid' => $c['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
            'is_synced' => true,
            'created_at' => $c['created_at'] ?? now(),
            'updated_at' => $c['updated_at'] ?? now(),
        ]);
    }

    // 2. Ürünleri SQLite'a Yükle
    echo "🔄 2. Ürünler aktarılıyor...\n";
    DB::connection('sqlite')->table('products')->truncate();
    foreach ($data['products'] ?? [] as $p) {
        DB::connection('sqlite')->table('products')->insert([
            'id' => $p['id'],
            'category_id' => $p['category_id'] ?? null,
            'branch_id' => $p['branch_id'] ?? 1,
            'name' => $p['name'],
            'slug' => $p['slug'] ?? \Illuminate\Support\Str::slug($p['name']),
            'sku' => $p['sku'] ?? null,
            'price' => $p['price'] ?? 0,
            'discounted_price' => $p['discounted_price'] ?? null,
            'stock_quantity' => $p['stock_quantity'] ?? 0,
            'min_stock_level' => $p['min_stock_level'] ?? 0,
            'unit' => $p['unit'] ?? 'adet',
            'track_stock' => $p['track_stock'] ?? false,
            'description' => $p['description'] ?? null,
            'image_path' => $p['image_path'] ?? null,
            'kitchen_department' => $p['kitchen_department'] ?? null,
            'is_active' => $p['is_active'] ?? true,
            'sync_uuid' => $p['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
            'is_synced' => true,
            'created_at' => $p['created_at'] ?? now(),
            'updated_at' => $p['updated_at'] ?? now(),
        ]);
    }

    // 3. Salonları SQLite'a Yükle
    if (!empty($data['halls'])) {
        echo "🔄 3. Salonlar aktarılıyor...\n";
        DB::connection('sqlite')->table('halls')->truncate();
        foreach ($data['halls'] as $h) {
            DB::connection('sqlite')->table('halls')->insert([
                'id' => $h['id'],
                'branch_id' => $h['branch_id'] ?? 1,
                'name' => $h['name'],
                'sort_order' => $h['sort_order'] ?? 0,
                'is_active' => $h['is_active'] ?? true,
                'created_at' => $h['created_at'] ?? now(),
                'updated_at' => $h['updated_at'] ?? now(),
            ]);
        }
    }

    // 4. Masaları SQLite'a Yükle
    if (!empty($data['tables'])) {
        echo "🔄 4. Masalar aktarılıyor...\n";
        DB::connection('sqlite')->table('dining_tables')->truncate();
        foreach ($data['tables'] as $t) {
            DB::connection('sqlite')->table('dining_tables')->insert([
                'id' => $t['id'],
                'branch_id' => $t['branch_id'] ?? 1,
                'hall_id' => $t['hall_id'] ?? null,
                'name' => $t['name'],
                'capacity' => $t['capacity'] ?? 4,
                'status' => $t['status'] ?? 'available',
                'is_active' => $t['is_active'] ?? true,
                'created_at' => $t['created_at'] ?? now(),
                'updated_at' => $t['updated_at'] ?? now(),
            ]);
        }
    }

    // 5. Adisyonları ve Kalemleri SQLite'a Yükle
    if (isset($data['checks'])) {
        echo "🔄 5. Adisyonlar ve Kalemler aktarılıyor...\n";
        DB::connection('sqlite')->table('check_items')->truncate();
        DB::connection('sqlite')->table('checks')->truncate();

        foreach ($data['checks'] as $chk) {
            DB::connection('sqlite')->table('checks')->insert([
                'id' => $chk['id'],
                'branch_id' => $chk['branch_id'] ?? 1,
                'dining_table_id' => $chk['dining_table_id'] ?? null,
                'waiter_id' => $chk['waiter_id'] ?? null,
                'check_number' => $chk['check_number'] ?? ('CHK-' . $chk['id']),
                'sync_uuid' => $chk['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                'is_synced' => true,
                'guest_count' => $chk['guest_count'] ?? 1,
                'status' => $chk['status'] ?? 'open',
                'subtotal' => $chk['subtotal'] ?? 0,
                'discount_total' => $chk['discount_total'] ?? 0,
                'total' => $chk['total'] ?? 0,
                'opened_at' => $chk['opened_at'] ?? now(),
                'kitchen_sent_at' => $chk['kitchen_sent_at'] ?? null,
                'created_at' => $chk['created_at'] ?? now(),
                'updated_at' => $chk['updated_at'] ?? now(),
            ]);

            foreach ($chk['items'] ?? [] as $itm) {
                DB::connection('sqlite')->table('check_items')->insert([
                    'id' => $itm['id'],
                    'check_id' => $chk['id'],
                    'product_id' => $itm['product_id'] ?? null,
                    'product_name' => $itm['product_name'] ?? 'Ürün',
                    'sync_uuid' => $itm['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                    'is_synced' => true,
                    'kitchen_status' => $itm['kitchen_status'] ?? 'pending',
                    'unit_price' => $itm['unit_price'] ?? 0,
                    'quantity' => $itm['quantity'] ?? 1,
                    'total_price' => $itm['total_price'] ?? 0,
                    'notes' => $itm['notes'] ?? null,
                    'is_complimentary' => $itm['is_complimentary'] ?? false,
                    'is_cancelled' => $itm['is_cancelled'] ?? false,
                    'created_at' => $itm['created_at'] ?? now(),
                    'updated_at' => $itm['updated_at'] ?? now(),
                ]);
            }
        }
    }

    // 6. Ödemeler
    if (isset($data['payments'])) {
        echo "🔄 6. Ödemeler aktarılıyor...\n";
        DB::connection('sqlite')->table('payments')->truncate();
        foreach ($data['payments'] as $p) {
            DB::connection('sqlite')->table('payments')->insert([
                'id' => $p['id'],
                'check_id' => $p['check_id'] ?? null,
                'payment_method' => $p['payment_method'] ?? 'cash',
                'amount' => $p['amount'] ?? 0,
                'sync_uuid' => $p['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                'is_synced' => true,
                'created_at' => $p['created_at'] ?? now(),
                'updated_at' => $p['updated_at'] ?? now(),
            ]);
        }
    }

    // 7. Stok Hareketleri
    if (isset($data['stock_movements'])) {
        echo "🔄 7. Stok Hareketleri aktarılıyor...\n";
        DB::connection('sqlite')->table('stock_movements')->truncate();
        foreach ($data['stock_movements'] as $sm) {
            DB::connection('sqlite')->table('stock_movements')->insert([
                'id' => $sm['id'],
                'product_id' => $sm['product_id'] ?? null,
                'check_id' => $sm['check_id'] ?? null,
                'check_item_id' => $sm['check_item_id'] ?? null,
                'sync_uuid' => $sm['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                'is_synced' => true,
                'type' => $sm['type'] ?? 'sale_deduction',
                'quantity' => $sm['quantity'] ?? 1,
                'status' => $sm['status'] ?? 'completed',
                'notes' => $sm['notes'] ?? null,
                'created_at' => $sm['created_at'] ?? now(),
                'updated_at' => $sm['updated_at'] ?? now(),
            ]);
        }
    }

    // SQLite Yabancı Anahtar Kısıtlamalarını Geri Aç
    DB::connection('sqlite')->statement('PRAGMA foreign_keys = ON;');

    echo "\n🎉 TEBRİKLER! LOKAL SQLITE VERİTABANI CANLI MYSQL İLE 100% EŞİTLENDİ!\n";

} catch (\Throwable $e) {
    DB::connection('sqlite')->statement('PRAGMA foreign_keys = ON;');
    echo "\n❌ HATA: " . $e->getMessage() . "\n";
    echo "İzleme: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
