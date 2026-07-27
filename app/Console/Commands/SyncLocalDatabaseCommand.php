<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncLocalDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-local {--fresh : Yerel SQLite verilerini tamamen temizleyip canlı MySQL verilerini sıfırdan çeker}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uzak MySQL sunucusundaki usta verileri yerel SQLite veritabanına senkronize eder.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 🔒 SERİLEŞTİRME: Aynı anda yalnızca TEK senkronizasyon süreci çalışabilir.
        // Her POS işlemi (ekle/sil/güncelle) ayrı bir arka plan sync süreci tetiklediğinden
        // süreçler üst üste biniyordu: eski (stale) PULL verisi, taze PUSH edilmiş
        // adisyon/kalem verilerini eziyor, silinenleri hortlatıyor, totalleri sıfırlıyordu.
        // Kilit alınamazsa sessizce çık — zaten koşan sync güncel veriyi işliyor ve
        // sonraki POS işlemi yeni bir sync tetikleyecek.
        $lockHandle = fopen(storage_path('framework/sync-local.lock'), 'c');
        if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle) fclose($lockHandle);
            $this->info('⏳ Başka bir senkronizasyon süreci zaten çalışıyor; bu çalıştırma atlandı.');
            return Command::SUCCESS;
        }

        try {
            return $this->runSync();
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function runSync(): int
    {
        $isFresh = $this->option('fresh');

        if ($isFresh) {
            $this->warn('🧹 Yerel SQLite verileri temizleniyor...');
            try {
                DB::connection('sqlite')->transaction(function () {
                    DB::connection('sqlite')->table('check_items')->delete();
                    DB::connection('sqlite')->table('checks')->delete();
                    DB::connection('sqlite')->table('payments')->delete();
                    DB::connection('sqlite')->table('dining_tables')->delete();
                    DB::connection('sqlite')->table('halls')->delete();
                    DB::connection('sqlite')->table('products')->delete();
                    DB::connection('sqlite')->table('categories')->delete();
                    DB::connection('sqlite')->table('staff_profiles')->delete();
                    DB::connection('sqlite')->table('users')->delete();
                });
                $this->info('✨ Yerel SQLite veritabanı başarıyla temizlendi.');
            } catch (\Throwable $e) {
                $this->warn('SQLite temizleme uyarısı: ' . $e->getMessage());
            }
        }

        $this->info('🌐 adisyon.synaptropic.com canlı verileri yerel çevrimdışı moda (SQLite) yükleniyor...');

        // 0. SQLite veritabanının ve tablolarının hazır olduğundan emin ol
        try {
            $sqlitePath = config('database.connections.sqlite.database');
            if (!file_exists($sqlitePath)) {
                @touch($sqlitePath);
            }
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'sqlite',
                '--force' => true,
            ]);
        } catch (\Throwable $mEx) {
            $this->warn('SQLite ilklendirme uyarısı: ' . $mEx->getMessage());
        }

        try {
            if (empty($apiKey)) {
                try {
                    $apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? '';
                } catch (\Throwable $e) {}
            }

            if (empty($apiKey)) {
                try {
                    $apiKey = DB::connection('sqlite')->table('devices')->whereNotNull('api_key')->value('api_key') ?? '';
                } catch (\Throwable $e) {}
            }

            if (empty($apiKey)) {
                $apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
            }

            try {
                DB::connection('sqlite')->table('settings')->updateOrInsert(
                    ['key' => 'DeviceApiKey'],
                    ['value' => $apiKey]
                );
            } catch (\Throwable $e) {}

            // 1. ÖNCE: Çevrimdışı modda yerelde oluşan henüz senkronize olmamış adisyon, ödeme ve stok hareketlerini canlı sunucuya PUSH et!
            $this->pushUnsyncedLocalDataToCloud($apiKey);

            // 2. SONRA: Canlı HTTPS API üzerinden güncel verileri PULL et!
            $apiUrl = config('services.adisyon.api_url', 'https://adisyon.synaptropic.com/api/v1/sync/pull');

            $response = Http::withoutVerifying()->timeout(30)->withHeaders([
                'X-Device-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->get($apiUrl);

            if ($response->successful() && $response->json('success')) {
                $payload = $response->json('data');
                $this->syncDataToSqlite(
                    collect($payload['users'] ?? []),
                    collect($payload['staff_profiles'] ?? []),
                    collect($payload['halls'] ?? []),
                    collect($payload['tables'] ?? []),
                    collect($payload['categories'] ?? []),
                    collect($payload['products'] ?? []),
                    collect($payload['checks'] ?? []),
                    collect($payload['settings'] ?? []),
                    collect($payload['delivery_integrations'] ?? []),
                    collect($payload['payments'] ?? []),
                    collect($payload['delivery_orders'] ?? []),
                    collect($payload['stock_movements'] ?? [])
                );
                $this->info('✅ adisyon.synaptropic.com canlı verileri yerel çevrimdışı moda başarıyla yüklendi.');

                // 3. PULL TAMAMLANDI! Tombstone'ları TAZE PULL verisine göre uzlaştır (reconcile).
                // ⚠️ ÖNEMLİ: Tombstone'u "synced" bayrağına güvenerek DEĞİL, sunucunun taze PULL'da
                // o kaydı hâlâ döndürüp döndürmediğine bakarak temizle:
                //   - Sunucu artık döndürmüyorsa  -> silme onaylandı -> tombstone'u sil.
                //   - Sunucu hâlâ döndürüyorsa     -> silme geçmemiş -> tombstone'u KORU + is_synced=0 yap
                //                                     (bir sonraki turda silme yeniden PUSH edilir; ürün yerelde hortlamaz).
                if (\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('deleted_records')) {
                    $this->reconcileTombstones('product', $payload['products'] ?? []);
                    $this->reconcileTombstones('category', $payload['categories'] ?? []);
                    $this->info('🗑️ Silme kayıtları taze PULL verisine göre uzlaştırıldı.');
                }

                Log::channel('sync')->info('[SYNC-PULL-SUCCESS] Canlı MySQL verileri yerel SQLite veritabanına aktarıldı.', [
                    'timestamp' => now()->toIso8601String(),
                    'counts' => [
                        'categories' => count($payload['categories'] ?? []),
                        'products' => count($payload['products'] ?? []),
                        'halls' => count($payload['halls'] ?? []),
                        'tables' => count($payload['tables'] ?? []),
                        'checks' => count($payload['checks'] ?? []),
                        'payments' => count($payload['payments'] ?? []),
                        'delivery_orders' => count($payload['delivery_orders'] ?? []),
                        'stock_movements' => count($payload['stock_movements'] ?? []),
                    ]
                ]);
                return Command::SUCCESS;
            }

            $this->error('Uzak sunucuya ulaşılamadı. Yerel veritabanı mevcut haliyle kullanılacak.');
            Log::channel('sync')->warning('[SYNC-PULL-OFFLINE] Canlı API sunucusuna ulaşılamadı. Yerel SQLite mevcut haliyle çalışıyor.', [
                'timestamp' => now()->toIso8601String(),
                'api_url' => $apiUrl,
                'status' => $response->status()
            ]);
            return Command::FAILURE;

        } catch (\Throwable $e) {
            $this->error('Senkronizasyon hatası: ' . $e->getMessage());
            Log::channel('sync')->error('[SYNC-PULL-ERROR] Yerel veritabanı senkronizasyon istisnası: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function syncDataToSqlite($users, $staff, $halls, $tables, $categories, $products, $checks, $settings = null, $integrations = null, $payments = null, $deliveryOrders = null, $stockMovements = null): void
    {
        DB::connection('sqlite')->statement('PRAGMA foreign_keys = OFF;');

        DB::connection('sqlite')->transaction(function () use ($users, $staff, $halls, $tables, $categories, $products, $checks, $settings, $integrations, $payments, $deliveryOrders, $stockMovements) {
            // Branches
            if (!DB::connection('sqlite')->table('branches')->where('id', 1)->exists()) {
                DB::connection('sqlite')->table('branches')->insert([
                    'id' => 1,
                    'name' => 'Merkez Şube',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Users
            foreach ($users as $u) {
                $uArr = (array) $u;
                if (isset($uArr['id'])) {
                    $matchKey = !empty($uArr['restaurant_id']) ? ['restaurant_id' => $uArr['restaurant_id']] : ['id' => $uArr['id']];
                    DB::connection('sqlite')->table('users')->updateOrInsert(
                        $matchKey,
                        [
                            'name' => $uArr['name'] ?? '',
                            'email' => $uArr['email'] ?? '',
                            'restaurant_id' => $uArr['restaurant_id'] ?? null,
                            'password' => $uArr['password'] ?? '',
                            'is_admin' => $uArr['is_admin'] ?? false,
                            'updated_at' => now(),
                        ]
                    );
                }
            }

            // Halls
            foreach ($halls as $h) {
                $hArr = (array) $h;
                DB::connection('sqlite')->table('halls')->updateOrInsert(
                    ['id' => $hArr['id']],
                    [
                        'branch_id' => $hArr['branch_id'] ?? 1,
                        'name' => $hArr['name'],
                        'sort_order' => $hArr['sort_order'] ?? 0,
                    ]
                );
            }

            // Dining Tables
            foreach ($tables as $t) {
                $tArr = (array) $t;
                if (isset($tArr['id'])) {
                    $tableName = $tArr['name'] ?? $tArr['table_number'] ?? ('Masa ' . $tArr['id']);
                    DB::connection('sqlite')->table('dining_tables')->updateOrInsert(
                        ['id' => $tArr['id']],
                        [
                            'hall_id' => $tArr['hall_id'] ?? 1,
                            'name' => $tableName,
                            'capacity' => $tArr['capacity'] ?? 4,
                            'status' => $tArr['status'] ?? 'available',
                        ]
                    );
                }
            }

            // Categories — Silme kayıtlarını UUID + Name + ID ile 3'lü filtrele
            $deletedCategoryUuids = [];
            $deletedCategoryNames = [];
            $deletedCategoryIds = [];
            if (\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('deleted_records')) {
                $delCatRecords = DB::connection('sqlite')->table('deleted_records')->where('type', 'category')->get();
                foreach ($delCatRecords as $dc) {
                    if (!empty($dc->sync_uuid)) $deletedCategoryUuids[] = $dc->sync_uuid;
                    if (!empty($dc->name)) $deletedCategoryNames[] = $dc->name;
                    if (!empty($dc->record_id)) $deletedCategoryIds[] = $dc->record_id;
                }
            }
            $serverCategorySyncUuids = [];
            foreach ($categories as $c) {
                $cArr = (array) $c;
                if (isset($cArr['id']) || isset($cArr['sync_uuid'])) {
                    $catSyncUuid = $cArr['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid();
                    $catName = $cArr['name'] ?? '';
                    $catId = $cArr['id'] ?? null;

                    // Silme filtresi: UUID veya İsim eşleşiyorsa bu kategori yerelde silinmiş, geri ekleme!
                    // (record_id yerel id olduğundan sunucu id'siyle karşılaştırılmaz — yanlış eşleşme yapardı.)
                    if (in_array($catSyncUuid, $deletedCategoryUuids, true) || in_array($catName, $deletedCategoryNames, true)) {
                        $serverCategorySyncUuids[] = $catSyncUuid;
                        continue;
                    }

                    $matchKey = !empty($cArr['sync_uuid']) ? ['sync_uuid' => $cArr['sync_uuid']] : ['id' => $cArr['id']];
                    
                    // ✅ Yerel SQLite'da kullanıcı tarafından güncellenmiş ve henüz PUSH edilmemiş (is_synced=0) kategori varsa ezme!
                    $existingCat = DB::connection('sqlite')->table('categories')->where($matchKey)->first();
                    if ($existingCat && ($existingCat->is_synced == false || $existingCat->is_synced == 0 || $existingCat->is_synced === null)) {
                        $serverCategorySyncUuids[] = $catSyncUuid;
                        continue;
                    }

                    DB::connection('sqlite')->table('categories')->updateOrInsert(
                        $matchKey,
                        [
                            'name' => $catName ?: 'Kategori',
                            'slug' => $cArr['slug'] ?? null,
                            'sort_order' => $cArr['sort_order'] ?? 0,
                            'is_active' => $cArr['is_active'] ?? true,
                            'sync_uuid' => $catSyncUuid,
                            'is_synced' => true,
                        ]
                    );
                    $serverCategorySyncUuids[] = $catSyncUuid;
                }
            }
            if (!empty($serverCategorySyncUuids)) {
                DB::connection('sqlite')->table('categories')
                    ->where('is_synced', true)
                    ->whereNotIn('sync_uuid', $serverCategorySyncUuids)
                    ->delete();
            }

            // Staff Profiles
            foreach ($staff as $st) {
                $sArr = (array) $st;
                if (isset($sArr['id'])) {
                    DB::connection('sqlite')->table('staff_profiles')->updateOrInsert(
                        ['id' => $sArr['id']],
                        [
                            'branch_id' => $sArr['branch_id'] ?? 1,
                            'name' => $sArr['name'],
                            'role' => $sArr['role'] ?? 'Garson',
                            'pin_code' => $sArr['pin_code'] ?? '1234',
                            'avatar_color' => $sArr['avatar_color'] ?? 'indigo',
                            'is_active' => $sArr['is_active'] ?? true,
                        ]
                    );
                }
            }

            // Products — Silme kayıtlarını UUID + Name + ID ile 3'lü filtrele
            $deletedProductUuids = [];
            $deletedProductNames = [];
            $deletedProductIds = [];
            if (\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('deleted_records')) {
                $delProdRecords = DB::connection('sqlite')->table('deleted_records')->where('type', 'product')->get();
                foreach ($delProdRecords as $dp) {
                    if (!empty($dp->sync_uuid)) $deletedProductUuids[] = $dp->sync_uuid;
                    if (!empty($dp->name)) $deletedProductNames[] = $dp->name;
                    if (!empty($dp->record_id)) $deletedProductIds[] = $dp->record_id;
                }
            }
            $serverProductSyncUuids = [];
            foreach ($products as $p) {
                $pArr = (array) $p;
                if (isset($pArr['id']) || isset($pArr['sync_uuid'])) {
                    $prodSyncUuid = $pArr['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid();
                    $prodName = $pArr['name'] ?? '';
                    $prodId = $pArr['id'] ?? null;

                    // Silme filtresi: UUID veya İsim eşleşiyorsa bu ürün yerelde silinmiş, geri ekleme!
                    // (record_id yerel id olduğundan sunucu id'siyle karşılaştırılmaz — yanlış eşleşme yapardı.)
                    if (in_array($prodSyncUuid, $deletedProductUuids, true) || in_array($prodName, $deletedProductNames, true)) {
                        $serverProductSyncUuids[] = $prodSyncUuid;
                        continue;
                    }

                    $matchKey = !empty($pArr['sync_uuid']) ? ['sync_uuid' => $pArr['sync_uuid']] : ['id' => $pArr['id']];
                    
                    // ✅ Yerel SQLite'da kullanıcı tarafından güncellenmiş ve henüz PUSH edilmemiş (is_synced=0) ürün varsa eski canlı veriyle ezme!
                    $existingProd = DB::connection('sqlite')->table('products')->where($matchKey)->first();
                    if ($existingProd && ($existingProd->is_synced == false || $existingProd->is_synced == 0 || $existingProd->is_synced === null)) {
                        $serverProductSyncUuids[] = $prodSyncUuid;
                        continue;
                    }

                    DB::connection('sqlite')->table('products')->updateOrInsert(
                        $matchKey,
                        [
                            'category_id' => $pArr['category_id'] ?? null,
                            'branch_id' => $pArr['branch_id'] ?? 1,
                            'name' => $prodName,
                            'slug' => $pArr['slug'] ?? \Illuminate\Support\Str::slug($prodName),
                            'sku' => $pArr['sku'] ?? null,
                            'price' => $pArr['price'] ?? 0,
                            'discounted_price' => $pArr['discounted_price'] ?? null,
                            'stock_quantity' => $pArr['stock_quantity'] ?? 0,
                            'min_stock_level' => $pArr['min_stock_level'] ?? 0,
                            'unit' => $pArr['unit'] ?? 'adet',
                            'track_stock' => $pArr['track_stock'] ?? false,
                            'description' => $pArr['description'] ?? null,
                            'image_path' => $pArr['image_path'] ?? null,
                            'kitchen_department' => $pArr['kitchen_department'] ?? null,
                            'is_active' => $pArr['is_active'] ?? true,
                            'sync_uuid' => $prodSyncUuid,
                            'is_synced' => true,
                        ]
                    );
                    $serverProductSyncUuids[] = $prodSyncUuid;
                }
            }
            if (!empty($serverProductSyncUuids)) {
                DB::connection('sqlite')->table('products')
                    ->where('is_synced', true)
                    ->whereNotIn('sync_uuid', $serverProductSyncUuids)
                    ->delete();
            }

            // Open Checks & Items
            foreach ($checks as $chk) {
                $cArr = (array) $chk;
                if (isset($cArr['id']) || isset($cArr['sync_uuid'])) {
                    $matchKey = !empty($cArr['sync_uuid']) ? ['sync_uuid' => $cArr['sync_uuid']] : ['id' => $cArr['id']];
                    
                    // ✅ Yerelde oluşturulmuş/güncellenmiş henüz senkronize olmamış adisyon varsa ezme!
                    $existingCheck = DB::connection('sqlite')->table('checks')->where($matchKey)->first();
                    if ($existingCheck && ($existingCheck->is_synced == false || $existingCheck->is_synced == 0 || $existingCheck->is_synced === null)) {
                        continue;
                    }

                    DB::connection('sqlite')->table('checks')->updateOrInsert(
                        $matchKey,
                        [
                            'branch_id' => $cArr['branch_id'] ?? 1,
                            'dining_table_id' => $cArr['dining_table_id'] ?? null,
                            'waiter_id' => $cArr['waiter_id'] ?? null,
                            'check_number' => $cArr['check_number'] ?? ('CHK-' . $cArr['id']),
                            'sync_uuid' => $cArr['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                            'is_synced' => true,
                            'guest_count' => $cArr['guest_count'] ?? 1,
                            'status' => $cArr['status'] ?? 'open',
                            'subtotal' => $cArr['subtotal'] ?? 0,
                            'discount_total' => $cArr['discount_total'] ?? 0,
                            'total' => $cArr['total'] ?? 0,
                            'opened_at' => $cArr['opened_at'] ?? now(),
                            'kitchen_sent_at' => $cArr['kitchen_sent_at'] ?? $cArr['opened_at'] ?? now(),
                        ]
                    );

                    // SQLite veritabanında oluşan/güncellenen adisyonun GERÇEK local ID'sini al!
                    $localCheckId = !empty($cArr['sync_uuid']) 
                        ? DB::connection('sqlite')->table('checks')->where('sync_uuid', $cArr['sync_uuid'])->value('id') 
                        : $cArr['id'];

                    // Masanın açık adisyon durumunu SQLite tarafında güncelle (Dolu/Boş)
                    if (!empty($cArr['dining_table_id'])) {
                        $tableStatus = ($cArr['status'] ?? '') === 'open' ? 'occupied' : 'available';
                        DB::connection('sqlite')->table('dining_tables')
                            ->where('id', $cArr['dining_table_id'])
                            ->update(['status' => $tableStatus]);
                    }

                    $items = $cArr['items'] ?? [];

                    // MySQL'den gelen item sync_uuid'lerini topla
                    $serverItemSyncUuids = [];
                    foreach ($items as $item) {
                        $iArr = (array) $item;
                        if (isset($iArr['id']) || isset($iArr['sync_uuid'])) {
                            $itemMatchKey = !empty($iArr['sync_uuid']) ? ['sync_uuid' => $iArr['sync_uuid']] : ['id' => $iArr['id']];
                            DB::connection('sqlite')->table('check_items')->updateOrInsert(
                                $itemMatchKey,
                                [
                                    'check_id' => $localCheckId,
                                    'product_id' => $iArr['product_id'] ?? null,
                                    'product_name' => !empty($iArr['product_name']) ? $iArr['product_name'] : ($iArr['product']['name'] ?? 'Özel Sipariş / Ürün'),
                                    'sync_uuid' => $iArr['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                                    'is_synced' => true,
                                    'kitchen_status' => $iArr['kitchen_status'] ?? 'pending',
                                    'unit_price' => $iArr['unit_price'] ?? 0,
                                    'quantity' => $iArr['quantity'] ?? 1,
                                    'total_price' => $iArr['total_price'] ?? 0,
                                    'notes' => $iArr['notes'] ?? null,
                                    'is_complimentary' => $iArr['is_complimentary'] ?? false,
                                    'is_cancelled' => $iArr['is_cancelled'] ?? false,
                                ]
                            );

                            if (!empty($iArr['sync_uuid'])) {
                                $serverItemSyncUuids[] = $iArr['sync_uuid'];
                            }
                        }
                    }

                    // ✅ MySQL'de artık olmayan (online'da silinen) item'ları SQLite'dan da sil!
                    // Sadece is_synced=true olanları sil (offline'da eklenen ama henüz push edilmemiş olanları korur)
                    // ⚠️ Sunucu boş kalem listesi döndüğünde de temizlik yapılmalı (tüm ürünler silinmiş olabilir)
                    $cleanupQuery = DB::connection('sqlite')->table('check_items')
                        ->where('check_id', $localCheckId)
                        ->where('is_synced', true);
                    if (!empty($serverItemSyncUuids)) {
                        $cleanupQuery->whereNotIn('sync_uuid', $serverItemSyncUuids);
                    }
                    $cleanupQuery->delete();
                }
            }

            // ✅ MySQL'den gelen checks listesinde olmayan KAPANMIŞ adisyonları SQLite'dan da temizle
            $serverCheckSyncUuids = collect($checks)->pluck('sync_uuid')->filter()->toArray();
            if (!empty($serverCheckSyncUuids)) {
                // Sadece is_synced=true ve status=closed olanları sil (offline'da açılan adisyonları korur)
                // Önce silinecek adisyonların ID'lerini al (ilişkili check_items ve masa durumu temizliği için)
                $orphanedCheckIds = DB::connection('sqlite')->table('checks')
                    ->where('is_synced', true)
                    ->where('status', 'closed')
                    ->whereNotIn('sync_uuid', $serverCheckSyncUuids)
                    ->pluck('id')->toArray();

                if (!empty($orphanedCheckIds)) {
                    // Silinecek adisyonlara ait yerel check_items'ları da temizle
                    DB::connection('sqlite')->table('check_items')
                        ->whereIn('check_id', $orphanedCheckIds)
                        ->delete();

                    DB::connection('sqlite')->table('checks')
                        ->whereIn('id', $orphanedCheckIds)
                        ->delete();
                }
            }

            // ✅ Sunucudaki adisyon durumlarına göre masa durumlarını güncelle
            // Sunucuda hiç açık adisyonu kalmayan masaları 'available' yap
            $allTableIds = DB::connection('sqlite')->table('dining_tables')->pluck('id')->toArray();
            foreach ($allTableIds as $tId) {
                $hasOpenCheck = DB::connection('sqlite')->table('checks')
                    ->where('dining_table_id', $tId)
                    ->where('status', 'open')
                    ->exists();
                DB::connection('sqlite')->table('dining_tables')
                    ->where('id', $tId)
                    ->update(['status' => $hasOpenCheck ? 'occupied' : 'available']);
            }

            // Payments (Raporlar için)
            if (!empty($payments)) {
                foreach ($payments as $p) {
                    $pArr = (array) $p;
                    if (isset($pArr['id'])) {
                        DB::connection('sqlite')->table('payments')->updateOrInsert(
                            ['id' => $pArr['id']],
                            [
                                'check_id' => $pArr['check_id'] ?? null,
                                'payment_method' => $pArr['payment_method'] ?? 'nakit',
                                'amount' => $pArr['amount'] ?? 0,
                                'created_at' => $pArr['created_at'] ?? now(),
                                'updated_at' => $pArr['updated_at'] ?? now(),
                            ]
                        );
                    }
                }
            }

            // Delivery Orders (Paket Servis için)
            if (!empty($deliveryOrders) && \Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('delivery_orders')) {
                foreach ($deliveryOrders as $do) {
                    $dArr = (array) $do;
                    if (isset($dArr['id'])) {
                        DB::connection('sqlite')->table('delivery_orders')->updateOrInsert(
                            ['id' => $dArr['id']],
                            [
                                'branch_id' => $dArr['branch_id'] ?? 1,
                                'channel' => $dArr['channel'] ?? 'getir',
                                'order_number' => $dArr['order_number'] ?? ('ORD-' . $dArr['id']),
                                'customer_name' => $dArr['customer_name'] ?? 'Müşteri',
                                'customer_phone' => $dArr['customer_phone'] ?? '',
                                'delivery_address' => $dArr['delivery_address'] ?? '',
                                'total' => $dArr['total'] ?? 0,
                                'status' => $dArr['status'] ?? 'new',
                                'payment_method' => $dArr['payment_method'] ?? 'online',
                                'items' => is_array($dArr['items'] ?? null) ? json_encode($dArr['items']) : ($dArr['items'] ?? '[]'),
                                'created_at' => $dArr['created_at'] ?? now(),
                                'updated_at' => $dArr['updated_at'] ?? now(),
                            ]
                        );
                    }
                }
            }

            // Stock Movements (Stok Hareketleri)
            if (!empty($stockMovements) && \Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('stock_movements')) {
                foreach ($stockMovements as $sm) {
                    $smArr = (array) $sm;
                    if (isset($smArr['id'])) {
                        DB::connection('sqlite')->table('stock_movements')->updateOrInsert(
                            ['id' => $smArr['id']],
                            [
                                'product_id' => $smArr['product_id'] ?? null,
                                'check_id' => $smArr['check_id'] ?? null,
                                'type' => $smArr['type'] ?? 'sale_deduction',
                                'quantity' => $smArr['quantity'] ?? 1,
                                'status' => $smArr['status'] ?? 'completed',
                                'notes' => $smArr['notes'] ?? null,
                                'created_at' => $smArr['created_at'] ?? now(),
                                'updated_at' => $smArr['updated_at'] ?? now(),
                            ]
                        );
                    }
                }
            }

            // Settings
            if (!empty($settings)) {
                foreach ($settings as $s) {
                    $sArr = (array) $s;
                    if (isset($sArr['key'])) {
                        DB::connection('sqlite')->table('settings')->updateOrInsert(
                            ['key' => $sArr['key']],
                            ['value' => $sArr['value'] ?? '']
                        );
                    }
                }
            }

            // Delivery Integrations
            if (!empty($integrations) && \Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('delivery_integrations')) {
                foreach ($integrations as $ig) {
                    $iArr = (array) $ig;
                    if (isset($iArr['channel'])) {
                        DB::connection('sqlite')->table('delivery_integrations')->updateOrInsert(
                            ['channel' => $iArr['channel']],
                            [
                                'store_name' => $iArr['store_name'] ?? null,
                                'store_id' => $iArr['store_id'] ?? null,
                                'api_key' => $iArr['api_key'] ?? null,
                                'is_active' => $iArr['is_active'] ?? true,
                                'auto_accept' => $iArr['auto_accept'] ?? false,
                            ]
                        );
                    }
                }
            }
        });
    }

    /**
     * Taze PULL verisine göre silme kayıtlarını (tombstone) uzlaştırır.
     * Sunucu kaydı hâlâ döndürüyorsa silme geçmemiştir: tombstone korunur ve yeniden
     * PUSH edilmek üzere is_synced=0 yapılır (hortlama engellenir). Sunucu artık
     * döndürmüyorsa silme onaylanmıştır ve tombstone güvenle temizlenir.
     *
     * NOT: Sunucu-varlık kontrolü YALNIZCA sync_uuid ve name ile yapılır. record_id
     * yerel SQLite id'sidir; sunucu id'siyle karşılaştırmak farklı kayıtları yanlışlıkla
     * eşleştireceğinden (yerel-id ≠ sunucu-id) bu kontrole dahil edilmez.
     */
    private function reconcileTombstones(string $type, $serverRecords): void
    {
        if (!\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('deleted_records')) {
            return;
        }

        $serverUuids = [];
        $serverNames = [];
        foreach ($serverRecords as $r) {
            $a = (array) $r;
            if (!empty($a['sync_uuid'])) $serverUuids[] = $a['sync_uuid'];
            if (!empty($a['name'])) $serverNames[] = $a['name'];
        }

        $tombstones = DB::connection('sqlite')->table('deleted_records')->where('type', $type)->get();
        foreach ($tombstones as $t) {
            $stillOnServer = (!empty($t->sync_uuid) && in_array($t->sync_uuid, $serverUuids, true))
                || (!empty($t->name) && in_array($t->name, $serverNames, true));

            if ($stillOnServer) {
                // Silme sunucuda gerçekleşmemiş -> tombstone'u koru, tekrar denenmek üzere is_synced=0 yap.
                DB::connection('sqlite')->table('deleted_records')
                    ->where('id', $t->id)
                    ->update(['is_synced' => false, 'updated_at' => now()]);
            } else {
                // Sunucu artık bu kaydı döndürmüyor -> silme onaylandı -> tombstone temizlenebilir.
                DB::connection('sqlite')->table('deleted_records')->where('id', $t->id)->delete();
            }
        }
    }

    /**
     * Çevrimdışı modda yerel SQLite veritabanında oluşan henüz senkronize edilmemiş (is_synced = 0)
     * adisyon, kalem, ödeme ve stok hareketlerini canlı MySQL sunucusuna PUSH eder.
     */
    private function pushUnsyncedLocalDataToCloud(string $apiKey): void
    {
        try {
            $unsyncedCheckIdsWithItems = DB::connection('sqlite')->table('check_items')
                ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
                ->pluck('check_id')->filter()->toArray();

            $unsyncedChecks = DB::connection('sqlite')->table('checks')
                ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced')->orWhereIn('id', $unsyncedCheckIdsWithItems))
                ->get();

            $unsyncedCheckItems = DB::connection('sqlite')->table('check_items')
                ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
                ->get();

            $unsyncedPayments = DB::connection('sqlite')->table('payments')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
            $unsyncedStockMovements = DB::connection('sqlite')->table('stock_movements')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
            $unsyncedCategories = DB::connection('sqlite')->table('categories')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
            $unsyncedProducts = DB::connection('sqlite')->table('products')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();

            $deletedProducts = [];
            $deletedCategories = [];
            if (\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('deleted_records')) {
                $pRecords = DB::connection('sqlite')->table('deleted_records')
                    ->where('type', 'product')
                    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
                    ->get();
                foreach ($pRecords as $pr) {
                    $deletedProducts[] = [
                        'sync_uuid' => $pr->sync_uuid,
                        'name' => $pr->name ?? null,
                        'record_id' => $pr->record_id ?? null,
                    ];
                }

                $cRecords = DB::connection('sqlite')->table('deleted_records')
                    ->where('type', 'category')
                    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
                    ->get();
                foreach ($cRecords as $cr) {
                    $deletedCategories[] = [
                        'sync_uuid' => $cr->sync_uuid,
                        'name' => $cr->name ?? null,
                        'record_id' => $cr->record_id ?? null,
                    ];
                }
            }

            if ($unsyncedChecks->isEmpty() && $unsyncedCheckItems->isEmpty() && $unsyncedPayments->isEmpty() && $unsyncedStockMovements->isEmpty() && $unsyncedCategories->isEmpty() && $unsyncedProducts->isEmpty() && empty($deletedProducts) && empty($deletedCategories)) {
                return;
            }

            $checksPayload = [];
            foreach ($unsyncedChecks as $check) {
                $items = DB::connection('sqlite')->table('check_items')->where('check_id', $check->id)->get();
                $itemsPayload = [];
                foreach ($items as $item) {
                    $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $item->product_id)->value('sync_uuid');
                    $itemsPayload[] = [
                        'sync_uuid' => $item->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
                        'product_id' => $item->product_id,
                        'product_sync_uuid' => $pSyncUuid,
                        'product_name' => $item->product_name ?? 'Ürün',
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => (float) $item->quantity,
                        'total_price' => (float) $item->total_price,
                        'status' => $item->kitchen_status ?? 'pending',
                        'is_cancelled' => (bool) ($item->is_cancelled ?? false),
                    ];
                }

                $checksPayload[] = [
                    'sync_uuid' => $check->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
                    // Bu payload check'in TÜM kalemlerini içerir; sunucu listede olmayan
                    // (uuid'siz eski kalıntılar dahil) kalemleri güvenle temizleyebilir.
                    'items_complete' => true,
                    'dining_table_id' => $check->dining_table_id,
                    'user_id' => $check->user_id ?? null,
                    'waiter_id' => $check->waiter_id,
                    'staff_profile_id' => $check->waiter_id,
                    'check_number' => $check->check_number ?? null,
                    'subtotal' => (float) ($check->subtotal ?? $check->total),
                    'discount_total' => (float) ($check->discount_total ?? 0),
                    'total' => (float) $check->total,
                    'total_amount' => (float) $check->total,
                    'discount_amount' => (float) ($check->discount_total ?? 0),
                    'status' => $check->status,
                    'created_at' => $check->created_at ?? (string) now(),
                    'items' => $itemsPayload,
                ];
            }

            $checkItemsPayload = [];
            foreach ($unsyncedCheckItems as $item) {
                $checkSyncUuid = DB::connection('sqlite')->table('checks')->where('id', $item->check_id)->value('sync_uuid');
                $diningTableId = DB::connection('sqlite')->table('checks')->where('id', $item->check_id)->value('dining_table_id');
                $checkItemsPayload[] = [
                    'sync_uuid' => $item->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
                    'check_sync_uuid' => $checkSyncUuid,
                    'dining_table_id' => $diningTableId,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name ?? 'Ürün',
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => (float) $item->quantity,
                    'total_price' => (float) $item->total_price,
                    'status' => $item->kitchen_status ?? 'pending',
                    'is_cancelled' => (bool) ($item->is_cancelled ?? false),
                ];
            }

            $paymentsPayload = [];
            foreach ($unsyncedPayments as $payment) {
                $checkSyncUuid = null;
                if ($payment->check_id) {
                    $checkSyncUuid = DB::connection('sqlite')->table('checks')->where('id', $payment->check_id)->value('sync_uuid');
                }

                $paymentsPayload[] = [
                    'sync_uuid' => $payment->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
                    'check_sync_uuid' => $checkSyncUuid,
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method ?? 'cash',
                    'created_at' => $payment->created_at ?? (string) now(),
                ];
            }

            $stockPayload = [];
            foreach ($unsyncedStockMovements as $stock) {
                $stockPayload[] = [
                    'sync_uuid' => $stock->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
                    'product_id' => $stock->product_id,
                    'type' => $stock->type,
                    'quantity' => (float) $stock->quantity,
                    'notes' => $stock->notes ?? null,
                ];
            }

            $categoriesPayload = [];
            foreach ($unsyncedCategories as $cat) {
                $categoriesPayload[] = [
                    'sync_uuid' => $cat->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
                    'name' => $cat->name,
                    'slug' => $cat->slug ?? \Illuminate\Support\Str::slug($cat->name),
                    'sort_order' => (int) ($cat->sort_order ?? 0),
                    'is_active' => (bool) ($cat->is_active ?? true),
                ];
            }

            $productsPayload = [];
            foreach ($unsyncedProducts as $prod) {
                $categorySyncUuid = DB::connection('sqlite')->table('categories')->where('id', $prod->category_id)->value('sync_uuid');
                $productsPayload[] = [
                    'sync_uuid' => $prod->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
                    'category_id' => $prod->category_id,
                    'category_sync_uuid' => $categorySyncUuid,
                    'name' => $prod->name,
                    'slug' => $prod->slug ?? \Illuminate\Support\Str::slug($prod->name),
                    'sku' => $prod->sku ?? null,
                    'price' => (float) $prod->price,
                    'discounted_price' => $prod->discounted_price ? (float) $prod->discounted_price : null,
                    'stock_quantity' => (float) ($prod->stock_quantity ?? 0),
                    'min_stock_level' => (float) ($prod->min_stock_level ?? 0),
                    'unit' => $prod->unit ?? 'adet',
                    'track_stock' => (bool) ($prod->track_stock ?? false),
                    'description' => $prod->description ?? null,
                    'kitchen_department' => $prod->kitchen_department ?? null,
                    'is_active' => (bool) ($prod->is_active ?? true),
                ];
            }

            $pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
            $response = Http::withoutVerifying()->timeout(15)->withHeaders([
                'X-Device-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->post($pushUrl, [
                'batch_id' => 'BATCH-' . time(),
                'checks' => $checksPayload,
                'check_items' => $checkItemsPayload,
                'payments' => $paymentsPayload,
                'stock_movements' => $stockPayload,
                'categories' => $categoriesPayload,
                'products' => $productsPayload,
                'deleted_products' => $deletedProducts,
                'deleted_categories' => $deletedCategories,
            ]);

            if ($response->successful() && $response->json('success')) {
                $syncedUuids = $response->json('synced_uuids') ?? [];

                if (!empty($syncedUuids)) {
                    DB::connection('sqlite')->table('checks')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
                    DB::connection('sqlite')->table('check_items')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
                    DB::connection('sqlite')->table('payments')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
                    DB::connection('sqlite')->table('stock_movements')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
                    DB::connection('sqlite')->table('categories')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
                    DB::connection('sqlite')->table('products')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
                    if (\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('deleted_records')) {
                        DB::connection('sqlite')->table('deleted_records')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
                        // ⚠️ Burada SİLME! Temizlik PULL'dan SONRA yapılacak.
                        // Böylece PULL sırasında silinmiş ürünler filtreden kaçmaz ve geri eklenmez.
                    }
                }

                // ✅ PUSH başarılı olduktan sonra offline'da iptal edilen item'ları SQLite'dan tamamen kaldır
                // (MySQL'e iptal bilgisi aktarıldığı için artık local'de tutmaya gerek yok)
                DB::connection('sqlite')->table('check_items')
                    ->where('is_cancelled', true)
                    ->where('is_synced', true)
                    ->delete();

                $this->info('📤 Yerel çevrimdışı veriler (' . count($syncedUuids) . ' adet) canlı MySQL sunucusuna başarıyla PUSH edildi.');
                Log::channel('sync')->info('[SYNC-PUSH-SUCCESS] Yerel SQLite verileri canlı MySQL sunucusuna PUSH edildi.', [
                    'timestamp' => now()->toIso8601String(),
                    'synced_count' => count($syncedUuids),
                    'synced_uuids' => $syncedUuids,
                    'checks_count' => count($checksPayload),
                    'payments_count' => count($paymentsPayload),
                    'stock_count' => count($stockPayload),
                ]);
            }
        } catch (\Throwable $e) {
            $this->warn('Çevrimdışı veri PUSH uyarısı: ' . $e->getMessage());
            Log::channel('sync')->warning('[SYNC-PUSH-WARN] Çevrimdışı PUSH aktarım uyarısı: ' . $e->getMessage(), [
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }
}
