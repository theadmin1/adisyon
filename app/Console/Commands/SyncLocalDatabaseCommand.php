<?php

namespace App\Console\Commands;

use App\Services\BidirectionalSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        if (! $lockHandle || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle) {
                fclose($lockHandle);
            }
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
                $this->warn('SQLite temizleme uyarısı: '.$e->getMessage());
            }
        }

        $this->info('🌐 adisyon.synaptropic.com canlı verileri yerel çevrimdışı moda (SQLite) yükleniyor...');

        // 0. SQLite veritabanının ve tablolarının hazır olduğundan emin ol
        try {
            $sqlitePath = config('database.connections.sqlite.database');
            if (! file_exists($sqlitePath)) {
                @touch($sqlitePath);
            }
            Artisan::call('migrate', [
                '--database' => 'sqlite',
                '--force' => true,
            ]);
        } catch (\Throwable $mEx) {
            $this->warn('SQLite ilklendirme uyarısı: '.$mEx->getMessage());
        }

        try {
            $apiKey = $this->resolveDeviceApiKey();

            if (empty($apiKey)) {
                $this->error('Cihaz API anahtarı bulunamadı. Lisans doğrulamasını yeniden çalıştırın.');

                return Command::FAILURE;
            }

            try {
                DB::connection('sqlite')->table('settings')->updateOrInsert(
                    ['key' => 'DeviceApiKey'],
                    ['value' => $apiKey]
                );
            } catch (\Throwable $e) {
            }

            $restaurantCredentials = $this->readCompanionRestaurantCredentials();

            if ($restaurantCredentials !== null) {
                $this->info('🔐 Çift yönlü senkronizasyon kayıtlı restoran kimliğiyle, şube-kısıtlı çalışıyor.');
            }

            // Yerel değişiklikler canlıya ulaşmadan PULL yapma. Aksi halde bağlantı
            // veya kimlik doğrulama hatasında çevrimdışı kayıtlar eski canlı
            // görüntünün altında kalabilir.
            if (! $this->pushUnsyncedLocalDataToCloud($apiKey, $restaurantCredentials)) {
                $this->error('Yerel değişiklikler canlı sunucuya gönderilemedi; güvenlik için PULL iptal edildi.');

                return Command::FAILURE;
            }

            // 2. SONRA: Canlı HTTPS API üzerinden güncel verileri PULL et!
            $apiUrl = $restaurantCredentials !== null
                ? config('services.adisyon.restaurant_pull_url', 'https://adisyon.synaptropic.com/api/v1/sync/pull/restaurant')
                : config('services.adisyon.api_url', 'https://adisyon.synaptropic.com/api/v1/sync/pull');

            $response = $restaurantCredentials !== null
                ? Http::timeout(60)->acceptJson()->post($apiUrl, $restaurantCredentials)
                : Http::timeout(60)->withHeaders([
                    'X-Device-Api-Key' => $apiKey,
                    'Accept' => 'application/json',
                ])->get($apiUrl);

            if ($response->successful() && $response->json('success')) {
                $payload = $response->json('data');
                $payload = $this->enrichLegacyRelationshipUuids($payload);
                $branchPayload = (array) ($payload['branch'] ?? []);
                $sourceBranchId = (int) ($branchPayload['id'] ?? 0);
                if ($sourceBranchId <= 0) {
                    throw new \RuntimeException('Sunucu yanıtında geçerli şube bilgisi bulunamadı.');
                }

                // The same branch can already exist locally with a different numeric
                // ID. Keep that local ID so device/license/settings references remain
                // valid, and remap all downloaded branch-scoped records to it.
                $branchCode = trim((string) ($branchPayload['code'] ?? ''));
                $existingBranchId = $branchCode !== ''
                    ? (int) DB::connection('sqlite')->table('branches')
                        ->where('code', $branchCode)
                        ->value('id')
                    : 0;
                $branchId = $existingBranchId > 0 ? $existingBranchId : $sourceBranchId;
                $branchPayload['id'] = $branchId;
                $payload['branch'] = $branchPayload;

                DB::connection('sqlite')->table('branches')->updateOrInsert(
                    ['id' => $branchId],
                    [
                        'name' => $branchPayload['name'] ?? 'Restoran',
                        'code' => $branchCode !== '' ? $branchCode : 'MERKEZ-01',
                        'is_active' => $branchPayload['is_active'] ?? true,
                        'updated_at' => now(),
                        'created_at' => $branchPayload['created_at'] ?? now(),
                    ]
                );

                $bidirectionalSync = app(BidirectionalSyncService::class);
                $bidirectionalSync->applyPull(
                    $branchId,
                    $payload['sync_resources'] ?? [],
                    $payload['sync_manifest'] ?? []
                );

                $this->syncDataToSqlite(
                    collect($payload['users'] ?? []),
                    collect(),
                    collect(),
                    collect(),
                    collect($payload['categories'] ?? []),
                    collect($payload['products'] ?? []),
                    collect($payload['checks'] ?? []),
                    collect(),
                    collect(),
                    collect($payload['payments'] ?? []),
                    collect(),
                    collect($payload['stock_movements'] ?? []),
                    $payload['branch'] ?? null
                );
                $bidirectionalSync->applyPull(
                    $branchId,
                    $payload['sync_resources'] ?? [],
                    $payload['sync_manifest'] ?? []
                );
                $this->info('✅ adisyon.synaptropic.com canlı verileri yerel çevrimdışı moda başarıyla yüklendi.');

                // 3. PULL TAMAMLANDI! Tombstone'ları TAZE PULL verisine göre uzlaştır (reconcile).
                // ⚠️ ÖNEMLİ: Tombstone'u "synced" bayrağına güvenerek DEĞİL, sunucunun taze PULL'da
                // o kaydı hâlâ döndürüp döndürmediğine bakarak temizle:
                //   - Sunucu artık döndürmüyorsa  -> silme onaylandı -> tombstone'u sil.
                //   - Sunucu hâlâ döndürüyorsa     -> silme geçmemiş -> tombstone'u KORU + is_synced=0 yap
                //                                     (bir sonraki turda silme yeniden PUSH edilir; ürün yerelde hortlamaz).
                if (Schema::connection('sqlite')->hasTable('deleted_records')) {
                    $this->reconcileTombstones('product', $payload['products'] ?? []);
                    $this->reconcileTombstones('category', $payload['categories'] ?? []);
                    $this->info('🗑️ Silme kayıtları taze PULL verisine göre uzlaştırıldı.');
                }

                $counts = [
                    'categories' => count($payload['categories'] ?? []),
                    'products' => count($payload['products'] ?? []),
                    'halls' => count($payload['halls'] ?? []),
                    'tables' => count($payload['tables'] ?? []),
                    'checks' => count($payload['checks'] ?? []),
                    'payments' => count($payload['payments'] ?? []),
                    'delivery_orders' => count($payload['delivery_orders'] ?? []),
                    'stock_movements' => count($payload['stock_movements'] ?? []),
                ];

                Log::channel('sync')->info('[SYNC-PULL-SUCCESS] Canlı MySQL verileri yerel SQLite veritabanına başarıyla aktarıldı.', [
                    'timestamp' => now()->toIso8601String(),
                    'counts' => $counts,
                ]);

                try {
                    DB::connection('sqlite')->table('offline_sync_logs')->insert([
                        'branch_id' => (int) data_get($payload, 'branch.id'),
                        'sync_uuid' => (string) Str::uuid(),
                        'payload_type' => 'pull',
                        'status' => 'success',
                        'details' => json_encode($counts),
                        'synced_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $lEx) {
                }

                return Command::SUCCESS;
            }

            $this->error('Uzak sunucuya ulaşılamadı. HTTP Status: '.$response->status());
            Log::channel('sync')->warning('[SYNC-PULL-FAILED] Canlı API sunucusuna ulaşılamadı.', [
                'timestamp' => now()->toIso8601String(),
                'api_url' => $apiUrl,
                'status' => $response->status(),
                'response_body' => substr($response->body(), 0, 500),
            ]);

            try {
                DB::connection('sqlite')->table('offline_sync_logs')->insert([
                    'branch_id' => DB::connection('sqlite')->table('branches')->value('id'),
                    'sync_uuid' => (string) Str::uuid(),
                    'payload_type' => 'pull',
                    'status' => 'error',
                    'error_message' => 'HTTP '.$response->status().': '.substr($response->body(), 0, 300),
                    'synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $lEx) {
            }

            return Command::FAILURE;

        } catch (\Throwable $e) {
            $this->error('Senkronizasyon hatası: '.$e->getMessage());
            Log::channel('sync')->error('[SYNC-PULL-ERROR] Yerel veritabanı senkronizasyon istisnası: '.$e->getMessage(), [
                'timestamp' => now()->toIso8601String(),
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'exception' => $e->getTraceAsString(),
            ]);

            try {
                DB::connection('sqlite')->table('offline_sync_logs')->insert([
                    'branch_id' => DB::connection('sqlite')->table('branches')->value('id'),
                    'sync_uuid' => (string) Str::uuid(),
                    'payload_type' => 'sync',
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                    'details' => json_encode(['file' => $e->getFile().':'.$e->getLine()]),
                    'synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $lEx) {
            }

            return Command::FAILURE;
        }
    }

    /**
     * Prefer the key exported to this process, then the companion Windows
     * service database. The latter is authoritative after a license handshake
     * rotates the device key; Laravel's own SQLite setting may still contain
     * the previous key when the local web server was started independently.
     */
    private function resolveDeviceApiKey(): string
    {
        $companionKey = $this->readCompanionDeviceApiKey();
        if ($companionKey !== '') {
            try {
                DB::connection('sqlite')->table('settings')->updateOrInsert(
                    ['key' => 'DeviceApiKey'],
                    ['value' => $companionKey]
                );
            } catch (\Throwable $e) {
                Log::channel('sync')->warning('[SYNC-KEY] Güncel cihaz anahtarı Laravel SQLite ayarına yazılamadı.', [
                    'message' => $e->getMessage(),
                ]);
            }

            return $companionKey;
        }

        $configuredKey = trim((string) config('services.adisyon.api_key', ''));
        if ($configuredKey !== '') {
            return $configuredKey;
        }

        try {
            return trim((string) (DB::connection('sqlite')
                ->table('settings')
                ->where('key', 'DeviceApiKey')
                ->value('value') ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }

    private function readCompanionDeviceApiKey(): string
    {
        return $this->readCompanionSetting('DeviceApiKey');
    }

    /**
     * @return array{restaurant_id: string, password: string}|null
     */
    private function readCompanionRestaurantCredentials(): ?array
    {
        $restaurantId = $this->readCompanionSetting('RestaurantLoginId');
        $password = $this->readCompanionSetting('RestaurantLoginPassword');

        if ($restaurantId === '' || $password === '') {
            return null;
        }

        return [
            'restaurant_id' => $restaurantId,
            'password' => $password,
        ];
    }

    private function readCompanionSetting(string $key): string
    {
        $configuredPath = trim((string) config('services.adisyon.companion_database', ''));
        $candidates = array_filter(array_unique([
            $configuredPath,
            base_path('altf4_device.db'),
            base_path('src/AltF4DeviceService.WebApi/altf4_device.db'),
        ]));

        foreach ($candidates as $path) {
            if (! is_file($path)) {
                continue;
            }

            try {
                $pdo = new \PDO('sqlite:'.$path, null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);
                $statement = $pdo->prepare(
                    'SELECT "Value" FROM "Settings" WHERE lower("Key") = lower(?) LIMIT 1'
                );
                $statement->execute([$key]);
                $value = trim((string) $statement?->fetchColumn());

                if ($value !== '') {
                    return $value;
                }
            } catch (\Throwable $e) {
                Log::channel('sync')->warning('[SYNC-SETTING] Cihaz servisi ayarı okunamadı.', [
                    'database' => $path,
                    'key' => $key,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return '';
    }

    /**
     * Legacy POS payloads contain server-local numeric foreign keys. Offline
     * SQLite IDs can legitimately differ, so enrich them from the generic UUID
     * resources before importing.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enrichLegacyRelationshipUuids(array $payload): array
    {
        $resourceRows = (array) ($payload['sync_resources'] ?? []);
        $uuidMap = static function (array $rows): array {
            $map = [];
            foreach ($rows as $row) {
                if (is_array($row) && ! empty($row['_source_id']) && ! empty($row['sync_uuid'])) {
                    $map[(int) $row['_source_id']] = (string) $row['sync_uuid'];
                }
            }

            return $map;
        };

        $tableUuids = $uuidMap((array) ($resourceRows['dining_tables'] ?? []));
        $staffUuids = $uuidMap((array) ($resourceRows['staff_profiles'] ?? []));
        $productUuids = collect($payload['products'] ?? [])
            ->filter(fn ($row) => ! empty($row['id']) && ! empty($row['sync_uuid']))
            ->mapWithKeys(fn ($row) => [(int) $row['id'] => (string) $row['sync_uuid']])
            ->all();
        $checkUuids = collect($payload['checks'] ?? [])
            ->filter(fn ($row) => ! empty($row['id']) && ! empty($row['sync_uuid']))
            ->mapWithKeys(fn ($row) => [(int) $row['id'] => (string) $row['sync_uuid']])
            ->all();

        $itemUuids = [];
        foreach ((array) ($payload['checks'] ?? []) as $check) {
            foreach ((array) data_get($check, 'items', []) as $item) {
                if (! empty($item['id']) && ! empty($item['sync_uuid'])) {
                    $itemUuids[(int) $item['id']] = (string) $item['sync_uuid'];
                }
            }
        }

        $categoryUuids = collect($payload['categories'] ?? [])
            ->filter(fn ($row) => ! empty($row['id']) && ! empty($row['sync_uuid']))
            ->mapWithKeys(fn ($row) => [(int) $row['id'] => (string) $row['sync_uuid']])
            ->all();

        $payload['products'] = collect($payload['products'] ?? [])->map(function ($product) use ($categoryUuids): array {
            $product = (array) $product;
            $catId = (int) ($product['category_id'] ?? 0);
            $product['category_sync_uuid'] ??= $categoryUuids[$catId] ?? null;

            return $product;
        })->all();

        $payload['checks'] = collect($payload['checks'] ?? [])->map(function ($check) use (
            $tableUuids,
            $staffUuids,
            $productUuids
        ): array {
            $check = (array) $check;
            $tableId = (int) ($check['dining_table_id'] ?? 0);
            $staffId = (int) ($check['waiter_staff_profile_id'] ?? 0);
            $check['dining_table_sync_uuid'] ??= $tableUuids[$tableId] ?? null;
            $check['waiter_staff_profile_sync_uuid'] ??= $staffUuids[$staffId] ?? null;
            $check['items'] = collect($check['items'] ?? [])->map(function ($item) use (
                $staffUuids,
                $productUuids
            ): array {
                $item = (array) $item;
                $productId = (int) ($item['product_id'] ?? 0);
                $staffId = (int) ($item['added_by_staff_profile_id'] ?? 0);
                $item['product_sync_uuid'] ??= $productUuids[$productId] ?? null;
                $item['added_by_staff_profile_sync_uuid'] ??= $staffUuids[$staffId] ?? null;

                return $item;
            })->all();

            return $check;
        })->all();

        $payload['payments'] = collect($payload['payments'] ?? [])->map(function ($payment) use ($checkUuids): array {
            $payment = (array) $payment;
            $checkId = (int) ($payment['check_id'] ?? 0);
            $payment['check_sync_uuid'] ??= $checkUuids[$checkId] ?? null;

            return $payment;
        })->all();

        $payload['stock_movements'] = collect($payload['stock_movements'] ?? [])->map(function ($movement) use (
            $productUuids,
            $checkUuids,
            $itemUuids
        ): array {
            $movement = (array) $movement;
            $movement['product_sync_uuid'] ??= $productUuids[(int) ($movement['product_id'] ?? 0)] ?? null;
            $movement['check_sync_uuid'] ??= $checkUuids[(int) ($movement['check_id'] ?? 0)] ?? null;
            $movement['check_item_sync_uuid'] ??= $itemUuids[(int) ($movement['check_item_id'] ?? 0)] ?? null;

            return $movement;
        })->all();

        return $payload;
    }

    private function syncDataToSqlite($users, $staff, $halls, $tables, $categories, $products, $checks, $settings = null, $integrations = null, $payments = null, $deliveryOrders = null, $stockMovements = null, $branch = null): void
    {
        $branchData = (array) $branch;
        $branchId = (int) ($branchData['id'] ?? 0);
        if ($branchId <= 0) {
            throw new \RuntimeException('Sunucu yanıtında geçerli şube bilgisi bulunamadı.');
        }

        DB::connection('sqlite')->statement('PRAGMA foreign_keys = OFF;');

        DB::connection('sqlite')->transaction(function () use ($users, $staff, $halls, $tables, $categories, $products, $checks, $settings, $integrations, $payments, $deliveryOrders, $stockMovements, $branchData, $branchId) {
            // Branches
            DB::connection('sqlite')->table('branches')->updateOrInsert(
                ['id' => $branchId],
                [
                    'name' => $branchData['name'] ?? 'Şube',
                    'code' => $branchData['code'] ?? null,
                    'is_active' => $branchData['is_active'] ?? true,
                    'updated_at' => now(),
                ]
            );

            // Users
            foreach ($users as $u) {
                $uArr = (array) $u;
                if (isset($uArr['id'])) {
                    $matchKey = ! empty($uArr['restaurant_id']) ? ['restaurant_id' => $uArr['restaurant_id']] : ['id' => $uArr['id']];
                    DB::connection('sqlite')->table('users')->updateOrInsert(
                        $matchKey,
                        [
                            'name' => $uArr['name'] ?? '',
                            'email' => $uArr['email'] ?? '',
                            'restaurant_id' => $uArr['restaurant_id'] ?? null,
                            'branch_id' => $branchId,
                            'password' => $uArr['password_hash'] ?? '',
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
                        'branch_id' => $branchId,
                        'name' => $hArr['name'],
                        'sort_order' => $hArr['sort_order'] ?? 0,
                    ]
                );
            }

            // Dining Tables
            foreach ($tables as $t) {
                $tArr = (array) $t;
                if (isset($tArr['id'])) {
                    $tableName = $tArr['name'] ?? $tArr['table_number'] ?? ('Masa '.$tArr['id']);
                    DB::connection('sqlite')->table('dining_tables')->updateOrInsert(
                        ['id' => $tArr['id']],
                        [
                            'hall_id' => $tArr['hall_id'] ?? null,
                            'branch_id' => $branchId,
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
            if (Schema::connection('sqlite')->hasTable('deleted_records')) {
                $delCatRecords = DB::connection('sqlite')->table('deleted_records')->where('type', 'category')->get();
                foreach ($delCatRecords as $dc) {
                    if (! empty($dc->sync_uuid)) {
                        $deletedCategoryUuids[] = $dc->sync_uuid;
                    }
                    if (! empty($dc->name)) {
                        $deletedCategoryNames[] = $dc->name;
                    }
                    if (! empty($dc->record_id)) {
                        $deletedCategoryIds[] = $dc->record_id;
                    }
                }
            }
            $serverCategorySyncUuids = [];
            foreach ($categories as $c) {
                $cArr = (array) $c;
                if (isset($cArr['id']) || isset($cArr['sync_uuid'])) {
                    $catSyncUuid = $cArr['sync_uuid'] ?? (string) Str::uuid();
                    $catName = $cArr['name'] ?? '';
                    $catId = $cArr['id'] ?? null;

                    // Silme filtresi: UUID veya İsim eşleşiyorsa bu kategori yerelde silinmiş, geri ekleme!
                    // (record_id yerel id olduğundan sunucu id'siyle karşılaştırılmaz — yanlış eşleşme yapardı.)
                    if (in_array($catSyncUuid, $deletedCategoryUuids, true) || in_array($catName, $deletedCategoryNames, true)) {
                        $serverCategorySyncUuids[] = $catSyncUuid;

                        continue;
                    }

                    $matchKey = ! empty($cArr['sync_uuid']) ? ['sync_uuid' => $cArr['sync_uuid']] : ['id' => $cArr['id']];

                    // ✅ Yerel SQLite'da kullanıcı tarafından güncellenmiş ve henüz PUSH edilmemiş (is_synced=0) kategori varsa ezme!
                    $existingCat = DB::connection('sqlite')->table('categories')->where($matchKey)->first();
                    if ($existingCat && ($existingCat->is_synced == false || $existingCat->is_synced == 0 || $existingCat->is_synced === null)) {
                        $serverCategorySyncUuids[] = $catSyncUuid;

                        continue;
                    }

                    DB::connection('sqlite')->table('categories')->updateOrInsert(
                        $matchKey,
                        [
                            'branch_id' => $branchId,
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
            if (! empty($serverCategorySyncUuids)) {
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
                            'branch_id' => $branchId,
                            'name' => $sArr['name'],
                            'role' => $sArr['role'] ?? 'Garson',
                            'pin_code' => 'migrated',
                            'pin_hash' => $sArr['pin_hash'] ?? null,
                            'pin_length' => $sArr['pin_length'] ?? 4,
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
            if (Schema::connection('sqlite')->hasTable('deleted_records')) {
                $delProdRecords = DB::connection('sqlite')->table('deleted_records')->where('type', 'product')->get();
                foreach ($delProdRecords as $dp) {
                    if (! empty($dp->sync_uuid)) {
                        $deletedProductUuids[] = $dp->sync_uuid;
                    }
                    if (! empty($dp->name)) {
                        $deletedProductNames[] = $dp->name;
                    }
                    if (! empty($dp->record_id)) {
                        $deletedProductIds[] = $dp->record_id;
                    }
                }
            }
            $serverProductSyncUuids = [];
            foreach ($products as $p) {
                $pArr = (array) $p;
                if (isset($pArr['id']) || isset($pArr['sync_uuid']) || isset($pArr['name'])) {
                    $prodSyncUuid = $pArr['sync_uuid'] ?? null;
                    $prodName = $pArr['name'] ?? '';

                    $existingProd = null;
                    if (! empty($prodSyncUuid)) {
                        $existingProd = DB::connection('sqlite')->table('products')->where('sync_uuid', $prodSyncUuid)->first();
                    }
                    if (! $existingProd && empty($prodSyncUuid) && ! empty($prodName)) {
                        $existingProd = DB::connection('sqlite')->table('products')->where('name', $prodName)->first();
                    }

                    // Silme filtresi: UUID veya İsim eşleşiyorsa bu ürün yerelde silinmiş, geri ekleme!
                    $isDeletedLocally = ! empty($prodSyncUuid)
                        ? in_array($prodSyncUuid, $deletedProductUuids, true)
                        : in_array($prodName, $deletedProductNames, true);
                    if ($isDeletedLocally) {
                        if ($prodSyncUuid) {
                            $serverProductSyncUuids[] = $prodSyncUuid;
                        }

                        continue;
                    }

                    // ✅ Eğer yerelde zaten geçerli sync_uuid'li ürün varsa ve gelen sunucu verisinde sync_uuid boşsa (sunucudaki eski seed kaydı), bunu atla!
                    if ($existingProd && ! empty($existingProd->sync_uuid) && empty($prodSyncUuid)) {
                        continue;
                    }

                    // ✅ Yerel SQLite'da kullanıcı tarafından güncellenmiş ve henüz PUSH edilmemiş (is_synced=0) ürün varsa eski canlı veriyle ezme!
                    if ($existingProd && ($existingProd->is_synced == false || $existingProd->is_synced == 0 || $existingProd->is_synced === null)) {
                        if ($prodSyncUuid) {
                            $serverProductSyncUuids[] = $prodSyncUuid;
                        }

                        continue;
                    }

                    $matchKey = $existingProd ? ['id' => $existingProd->id] : (! empty($prodSyncUuid) ? ['sync_uuid' => $prodSyncUuid] : ['name' => $prodName]);

                    if (empty($prodSyncUuid) && $existingProd && ! empty($existingProd->sync_uuid)) {
                        $prodSyncUuid = $existingProd->sync_uuid;
                    }
                    if (empty($prodSyncUuid)) {
                        $prodSyncUuid = (string) Str::uuid();
                    }

                    $localCatId = null;
                    if (! empty($pArr['category_sync_uuid'])) {
                        $localCatId = DB::connection('sqlite')->table('categories')->where('sync_uuid', $pArr['category_sync_uuid'])->value('id');
                    }
                    if (! $localCatId && ! empty($pArr['category_id'])) {
                        if (DB::connection('sqlite')->table('categories')->where('id', $pArr['category_id'])->exists()) {
                            $localCatId = $pArr['category_id'];
                        }
                    }
                    if (! $localCatId) {
                        $localCatId = DB::connection('sqlite')->table('categories')->first()?->id ?? 1;
                    }

                    DB::connection('sqlite')->table('products')->updateOrInsert(
                        $matchKey,
                        [
                            'category_id' => $localCatId,
                            'branch_id' => $branchId,
                            'name' => $prodName,
                            'slug' => $pArr['slug'] ?? Str::slug($prodName),
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
                            'send_to_kitchen' => $pArr['send_to_kitchen'] ?? true,
                            'is_active' => $pArr['is_active'] ?? true,
                            'sync_uuid' => $prodSyncUuid,
                            'is_synced' => true,
                        ]
                    );
                    $serverProductSyncUuids[] = $prodSyncUuid;
                }
            }
            if (! empty($serverProductSyncUuids)) {
                DB::connection('sqlite')->table('products')
                    ->where('is_synced', true)
                    ->whereNotIn('sync_uuid', $serverProductSyncUuids)
                    ->delete();
            }

            // Open Checks & Items
            foreach ($checks as $chk) {
                $cArr = (array) $chk;
                if (isset($cArr['id']) || isset($cArr['sync_uuid']) || isset($cArr['check_number'])) {
                    $existingCheck = null;
                    if (! empty($cArr['sync_uuid'])) {
                        $existingCheck = DB::connection('sqlite')->table('checks')->where('sync_uuid', $cArr['sync_uuid'])->first();
                    }
                    if (! $existingCheck && ! empty($cArr['check_number'])) {
                        $existingCheck = DB::connection('sqlite')->table('checks')->where('check_number', $cArr['check_number'])->first();
                    }
                    if (! $existingCheck && ! empty($cArr['id'])) {
                        $existingCheck = DB::connection('sqlite')->table('checks')->where('id', $cArr['id'])->first();
                    }

                    // Yerelde oluşturulmuş henüz senkronize olmamış adisyon varsa ezme, ama sync_uuid'sini sunucuyla eşle
                    if ($existingCheck && ($existingCheck->is_synced == false || $existingCheck->is_synced == 0 || $existingCheck->is_synced === null)) {
                        if (! empty($cArr['sync_uuid']) && empty($existingCheck->sync_uuid)) {
                            DB::connection('sqlite')->table('checks')->where('id', $existingCheck->id)->update(['sync_uuid' => $cArr['sync_uuid']]);
                        }

                        continue;
                    }

                    $matchKey = $existingCheck
                        ? ['id' => $existingCheck->id]
                        : (! empty($cArr['check_number']) ? ['check_number' => $cArr['check_number']] : ['sync_uuid' => $cArr['sync_uuid']]);

                    $localDiningTableId = null;
                    if (! empty($cArr['dining_table_sync_uuid'])) {
                        $localDiningTableId = DB::connection('sqlite')->table('dining_tables')
                            ->where('sync_uuid', $cArr['dining_table_sync_uuid'])
                            ->value('id');
                    } elseif (! empty($cArr['dining_table_id'])
                        && DB::connection('sqlite')->table('dining_tables')->where('id', $cArr['dining_table_id'])->exists()) {
                        $localDiningTableId = $cArr['dining_table_id'];
                    }

                    $localWaiterStaffId = null;
                    if (! empty($cArr['waiter_staff_profile_sync_uuid'])) {
                        $localWaiterStaffId = DB::connection('sqlite')->table('staff_profiles')
                            ->where('sync_uuid', $cArr['waiter_staff_profile_sync_uuid'])
                            ->value('id');
                    }

                    DB::connection('sqlite')->table('checks')->updateOrInsert(
                        $matchKey,
                        [
                            'branch_id' => $branchId,
                            'dining_table_id' => $localDiningTableId,
                            'waiter_id' => $cArr['waiter_id'] ?? null,
                            'waiter_staff_profile_id' => $localWaiterStaffId,
                            'waiter_name' => $cArr['waiter_name'] ?? null,
                            'customer_notes' => $cArr['customer_notes'] ?? null,
                            'check_number' => $cArr['check_number'] ?? ('CHK-'.$cArr['id']),
                            'sync_uuid' => $cArr['sync_uuid'] ?? (string) Str::uuid(),
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
                    $localCheckId = ! empty($cArr['sync_uuid'])
                        ? DB::connection('sqlite')->table('checks')->where('sync_uuid', $cArr['sync_uuid'])->value('id')
                        : $cArr['id'];

                    // Masanın açık adisyon durumunu SQLite tarafında güncelle (Dolu/Boş)
                    if ($localDiningTableId) {
                        $tableStatus = match ($cArr['status'] ?? '') {
                            'open' => 'occupied',
                            'awaiting_payment' => 'awaiting_payment',
                            default => 'available',
                        };
                        DB::connection('sqlite')->table('dining_tables')
                            ->where('id', $localDiningTableId)
                            ->update(['status' => $tableStatus]);
                    }

                    $items = $cArr['items'] ?? [];

                    // MySQL'den gelen item sync_uuid'lerini topla
                    $serverItemSyncUuids = [];
                    foreach ($items as $item) {
                        $iArr = (array) $item;
                        if (isset($iArr['id']) || isset($iArr['sync_uuid'])) {
                            $itemMatchKey = ! empty($iArr['sync_uuid']) ? ['sync_uuid' => $iArr['sync_uuid']] : ['id' => $iArr['id']];

                            $localProdId = null;
                            $pSyncUuid = $iArr['product_sync_uuid'] ?? ($iArr['product']['sync_uuid'] ?? null);
                            if (! empty($pSyncUuid)) {
                                $localProdId = DB::connection('sqlite')->table('products')->where('sync_uuid', $pSyncUuid)->value('id');
                            }
                            if (! $localProdId && ! empty($iArr['product_name'])) {
                                $localProdId = DB::connection('sqlite')->table('products')->where('name', $iArr['product_name'])->value('id');
                            }
                            if (! $localProdId && ! empty($iArr['product_id'])) {
                                if (DB::connection('sqlite')->table('products')->where('id', $iArr['product_id'])->exists()) {
                                    $localProdId = $iArr['product_id'];
                                }
                            }

                            $localAddedByStaffId = null;
                            if (! empty($iArr['added_by_staff_profile_sync_uuid'])) {
                                $localAddedByStaffId = DB::connection('sqlite')->table('staff_profiles')
                                    ->where('sync_uuid', $iArr['added_by_staff_profile_sync_uuid'])
                                    ->value('id');
                            }

                            DB::connection('sqlite')->table('check_items')->updateOrInsert(
                                $itemMatchKey,
                                [
                                    'branch_id' => $branchId,
                                    'check_id' => $localCheckId,
                                    'product_id' => $localProdId,
                                    'added_by_staff_profile_id' => $localAddedByStaffId,
                                    'added_by_name' => $iArr['added_by_name'] ?? null,
                                    'product_name' => ! empty($iArr['product_name']) ? $iArr['product_name'] : ($iArr['product']['name'] ?? 'Özel Sipariş / Ürün'),
                                    'sync_uuid' => $iArr['sync_uuid'] ?? (string) Str::uuid(),
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

                            if (! empty($iArr['sync_uuid'])) {
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
                    if (! empty($serverItemSyncUuids)) {
                        $cleanupQuery->whereNotIn('sync_uuid', $serverItemSyncUuids);
                    }
                    $cleanupQuery->delete();
                }
            }

            // ✅ MySQL'den gelen checks listesinde olmayan KAPANMIŞ adisyonları SQLite'dan da temizle
            $serverCheckSyncUuids = collect($checks)->pluck('sync_uuid')->filter()->toArray();
            if (! empty($serverCheckSyncUuids)) {
                // Sadece is_synced=true ve status=closed olanları sil (offline'da açılan adisyonları korur)
                // Önce silinecek adisyonların ID'lerini al (ilişkili check_items ve masa durumu temizliği için)
                $orphanedCheckIds = DB::connection('sqlite')->table('checks')
                    ->where('is_synced', true)
                    ->where('status', 'closed')
                    ->whereNotIn('sync_uuid', $serverCheckSyncUuids)
                    ->pluck('id')->toArray();

                if (! empty($orphanedCheckIds)) {
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
                $activeStatus = DB::connection('sqlite')->table('checks')
                    ->where('dining_table_id', $tId)
                    ->whereIn('status', ['open', 'awaiting_payment'])
                    ->orderByRaw("CASE WHEN status = 'awaiting_payment' THEN 0 ELSE 1 END")
                    ->value('status');
                DB::connection('sqlite')->table('dining_tables')
                    ->where('id', $tId)
                    ->update([
                        'status' => match ($activeStatus) {
                            'awaiting_payment' => 'awaiting_payment',
                            'open' => 'occupied',
                            default => 'available',
                        },
                    ]);
            }

            // Payments (Raporlar için)
            if (! empty($payments)) {
                foreach ($payments as $p) {
                    $pArr = (array) $p;
                    if (isset($pArr['id']) || isset($pArr['sync_uuid'])) {
                        $localPaymentCheckId = ! empty($pArr['check_sync_uuid'])
                            ? DB::connection('sqlite')->table('checks')->where('sync_uuid', $pArr['check_sync_uuid'])->value('id')
                            : null;
                        if (! $localPaymentCheckId && ! empty($pArr['check_id'])
                            && DB::connection('sqlite')->table('checks')->where('id', $pArr['check_id'])->exists()) {
                            $localPaymentCheckId = $pArr['check_id'];
                        }

                        $paymentMatchKey = ! empty($pArr['sync_uuid'])
                            ? ['sync_uuid' => $pArr['sync_uuid']]
                            : ['id' => $pArr['id']];
                        DB::connection('sqlite')->table('payments')->updateOrInsert(
                            $paymentMatchKey,
                            [
                                'branch_id' => $branchId,
                                'check_id' => $localPaymentCheckId,
                                'sync_uuid' => $pArr['sync_uuid'] ?? (string) Str::uuid(),
                                'is_synced' => true,
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
            if (! empty($deliveryOrders) && Schema::connection('sqlite')->hasTable('delivery_orders')) {
                foreach ($deliveryOrders as $do) {
                    $dArr = (array) $do;
                    if (isset($dArr['id'])) {
                        DB::connection('sqlite')->table('delivery_orders')->updateOrInsert(
                            ['id' => $dArr['id']],
                            [
                                'branch_id' => $branchId,
                                'channel' => $dArr['channel'] ?? 'getir',
                                'order_number' => $dArr['order_number'] ?? ('ORD-'.$dArr['id']),
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
            if (! empty($stockMovements) && Schema::connection('sqlite')->hasTable('stock_movements')) {
                foreach ($stockMovements as $sm) {
                    $smArr = (array) $sm;
                    if (isset($smArr['id']) || isset($smArr['sync_uuid'])) {
                        $matchKey = ! empty($smArr['sync_uuid']) ? ['sync_uuid' => $smArr['sync_uuid']] : ['id' => $smArr['id']];

                        $localSmProdId = null;
                        $pSyncUuid = $smArr['product_sync_uuid'] ?? ($smArr['product']['sync_uuid'] ?? null);
                        if (! empty($pSyncUuid)) {
                            $localSmProdId = DB::connection('sqlite')->table('products')->where('sync_uuid', $pSyncUuid)->value('id');
                        }
                        if (! $localSmProdId && ! empty($smArr['product_id'])) {
                            if (DB::connection('sqlite')->table('products')->where('id', $smArr['product_id'])->exists()) {
                                $localSmProdId = $smArr['product_id'];
                            }
                        }
                        if (! $localSmProdId) {
                            $localSmProdId = DB::connection('sqlite')->table('products')->first()?->id ?? 1;
                        }

                        $localStockCheckId = ! empty($smArr['check_sync_uuid'])
                            ? DB::connection('sqlite')->table('checks')->where('sync_uuid', $smArr['check_sync_uuid'])->value('id')
                            : null;
                        $localStockCheckItemId = ! empty($smArr['check_item_sync_uuid'])
                            ? DB::connection('sqlite')->table('check_items')->where('sync_uuid', $smArr['check_item_sync_uuid'])->value('id')
                            : null;

                        DB::connection('sqlite')->table('stock_movements')->updateOrInsert(
                            $matchKey,
                            [
                                'sync_uuid' => $smArr['sync_uuid'] ?? (string) Str::uuid(),
                                'is_synced' => true,
                                'product_id' => $localSmProdId,
                                'check_id' => $localStockCheckId,
                                'check_item_id' => $localStockCheckItemId,
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
            if (! empty($settings)) {
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
            if (! empty($integrations) && Schema::connection('sqlite')->hasTable('delivery_integrations')) {
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
        if (! Schema::connection('sqlite')->hasTable('deleted_records')) {
            return;
        }

        $serverUuids = [];
        $serverNames = [];
        foreach ($serverRecords as $r) {
            $a = (array) $r;
            if (! empty($a['sync_uuid'])) {
                $serverUuids[] = $a['sync_uuid'];
            }
            if (! empty($a['name'])) {
                $serverNames[] = $a['name'];
            }
        }

        $tombstones = DB::connection('sqlite')->table('deleted_records')->where('type', $type)->get();
        foreach ($tombstones as $t) {
            $stillOnServer = (! empty($t->sync_uuid) && in_array($t->sync_uuid, $serverUuids, true))
                || (! empty($t->name) && in_array($t->name, $serverNames, true));

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
    /**
     * @param  array{restaurant_id: string, password: string}|null  $restaurantCredentials
     */
    private function pushUnsyncedLocalDataToCloud(
        string $apiKey,
        ?array $restaurantCredentials = null
    ): bool
    {
        try {
            $branchId = (int) DB::connection('sqlite')->table('branches')->value('id');
            $genericChanges = app(BidirectionalSyncService::class)
                ->collectLocalChanges($branchId);

            $unsyncedCheckIdsWithItems = DB::connection('sqlite')->table('check_items')
                ->where(fn ($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
                ->pluck('check_id')->filter()->toArray();

            $unsyncedChecks = DB::connection('sqlite')->table('checks')
                ->where(fn ($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced')->orWhereIn('id', $unsyncedCheckIdsWithItems))
                ->get();

            $unsyncedCheckItems = DB::connection('sqlite')->table('check_items')
                ->where(fn ($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
                ->get();

            $unsyncedPayments = DB::connection('sqlite')->table('payments')->where(fn ($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
            $unsyncedStockMovements = DB::connection('sqlite')->table('stock_movements')->where(fn ($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
            $unsyncedCategories = DB::connection('sqlite')->table('categories')->where(fn ($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
            $unsyncedProducts = DB::connection('sqlite')->table('products')->where(fn ($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();

            // Stok hareketi olan ürünlerin güncel stok miktarını da PUSH payload'ına ekle
            if ($unsyncedStockMovements->isNotEmpty()) {
                $stockProductIds = $unsyncedStockMovements->pluck('product_id')->filter()->unique()->toArray();
                if (! empty($stockProductIds)) {
                    $extraProducts = DB::connection('sqlite')->table('products')->whereIn('id', $stockProductIds)->get();
                    $existingProductIds = $unsyncedProducts->pluck('id')->toArray();
                    foreach ($extraProducts as $ep) {
                        if (! in_array($ep->id, $existingProductIds, true)) {
                            $unsyncedProducts->push($ep);
                        }
                    }
                }
            }

            $deletedProducts = [];
            $deletedCategories = [];
            if (Schema::connection('sqlite')->hasTable('deleted_records')) {
                $pRecords = DB::connection('sqlite')->table('deleted_records')
                    ->where('type', 'product')
                    ->where(fn ($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
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
                    ->where(fn ($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
                    ->get();
                foreach ($cRecords as $cr) {
                    $deletedCategories[] = [
                        'sync_uuid' => $cr->sync_uuid,
                        'name' => $cr->name ?? null,
                        'record_id' => $cr->record_id ?? null,
                    ];
                }
            }

            if ($unsyncedChecks->isEmpty()
                && $unsyncedCheckItems->isEmpty()
                && $unsyncedPayments->isEmpty()
                && $unsyncedStockMovements->isEmpty()
                && $unsyncedCategories->isEmpty()
                && $unsyncedProducts->isEmpty()
                && empty($deletedProducts)
                && empty($deletedCategories)
                && empty($genericChanges['resources'])
                && empty($genericChanges['deleted_resources'])) {
                return true;
            }

            $checksPayload = [];
            foreach ($unsyncedChecks as $check) {
                $items = DB::connection('sqlite')->table('check_items')->where('check_id', $check->id)->get();
                $diningTableSyncUuid = $check->dining_table_id
                    ? DB::connection('sqlite')->table('dining_tables')->where('id', $check->dining_table_id)->value('sync_uuid')
                    : null;
                $waiterStaffSyncUuid = $check->waiter_staff_profile_id
                    ? DB::connection('sqlite')->table('staff_profiles')->where('id', $check->waiter_staff_profile_id)->value('sync_uuid')
                    : null;
                $itemsPayload = [];
                foreach ($items as $item) {
                    $pSyncUuid = $item->product_id ? DB::connection('sqlite')->table('products')->where('id', $item->product_id)->value('sync_uuid') : null;
                    $addedByStaffSyncUuid = $item->added_by_staff_profile_id
                        ? DB::connection('sqlite')->table('staff_profiles')->where('id', $item->added_by_staff_profile_id)->value('sync_uuid')
                        : null;
                    $itemsPayload[] = [
                        'sync_uuid' => $item->sync_uuid ?? (string) Str::uuid(),
                        'product_id' => (int) ($item->product_id ?: 1),
                        'product_sync_uuid' => $pSyncUuid,
                        'added_by_staff_profile_sync_uuid' => $addedByStaffSyncUuid,
                        'added_by_name' => $item->added_by_name ?? null,
                        'product_name' => $item->product_name ?? 'Ürün',
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => (float) $item->quantity,
                        'total_price' => (float) $item->total_price,
                        'notes' => $item->notes ?? null,
                        'is_complimentary' => (bool) ($item->is_complimentary ?? false),
                        'complimentary_reason' => $item->complimentary_reason ?? null,
                        'status' => $item->kitchen_status ?? 'pending',
                        'is_cancelled' => (bool) ($item->is_cancelled ?? false),
                    ];
                }

                $checksPayload[] = [
                    'sync_uuid' => $check->sync_uuid ?? (string) Str::uuid(),
                    // Bu payload check'in TÜM kalemlerini içerir; sunucu listede olmayan
                    // (uuid'siz eski kalıntılar dahil) kalemleri güvenle temizleyebilir.
                    'items_complete' => true,
                    'dining_table_id' => $check->dining_table_id,
                    'dining_table_sync_uuid' => $diningTableSyncUuid,
                    'user_id' => null,
                    // ✅ waiter_id/staff_profile_id null gönder: SQLite'daki yerel user ID'ler
                    // MySQL users tablosunda mevcut olmayabilir ve FK constraint violation
                    // tüm PUSH transaction'ını çökertiyor (HTTP 500).
                    'waiter_id' => null,
                    'staff_profile_id' => null,
                    'waiter_staff_profile_sync_uuid' => $waiterStaffSyncUuid,
                    'waiter_name' => $check->waiter_name ?? null,
                    'customer_notes' => $check->customer_notes ?? null,
                    'check_number' => $check->check_number ?? null,
                    'guest_count' => (int) ($check->guest_count ?? 1),
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
                $diningTableSyncUuid = $diningTableId
                    ? DB::connection('sqlite')->table('dining_tables')->where('id', $diningTableId)->value('sync_uuid')
                    : null;
                $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $item->product_id)->value('sync_uuid');
                $addedByStaffSyncUuid = $item->added_by_staff_profile_id
                    ? DB::connection('sqlite')->table('staff_profiles')->where('id', $item->added_by_staff_profile_id)->value('sync_uuid')
                    : null;
                $checkItemsPayload[] = [
                    'sync_uuid' => $item->sync_uuid ?? (string) Str::uuid(),
                    'check_sync_uuid' => $checkSyncUuid,
                    'dining_table_id' => $diningTableId,
                    'dining_table_sync_uuid' => $diningTableSyncUuid,
                    'product_id' => (int) ($item->product_id ?: 1),
                    'product_sync_uuid' => $pSyncUuid,
                    'added_by_staff_profile_sync_uuid' => $addedByStaffSyncUuid,
                    'added_by_name' => $item->added_by_name ?? null,
                    'product_name' => $item->product_name ?? 'Ürün',
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => (float) $item->quantity,
                    'total_price' => (float) $item->total_price,
                    'notes' => $item->notes ?? null,
                    'is_complimentary' => (bool) ($item->is_complimentary ?? false),
                    'complimentary_reason' => $item->complimentary_reason ?? null,
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
                    'sync_uuid' => $payment->sync_uuid ?? (string) Str::uuid(),
                    'check_sync_uuid' => $checkSyncUuid,
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method ?? 'cash',
                    'created_at' => $payment->created_at ?? (string) now(),
                ];
            }

            $stockPayload = [];
            foreach ($unsyncedStockMovements as $stock) {
                $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $stock->product_id)->value('sync_uuid');

                if (empty($pSyncUuid) && ! empty($stock->product_id)) {
                    $pSyncUuid = DB::connection('sqlite')->table('products')->where('sync_uuid', $stock->product_id)->value('sync_uuid');
                }

                if (empty($pSyncUuid) && ! empty($stock->check_id)) {
                    $checkItemProdId = DB::connection('sqlite')->table('check_items')->where('check_id', $stock->check_id)->value('product_id');
                    if ($checkItemProdId) {
                        $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $checkItemProdId)->value('sync_uuid');
                    }
                }

                $stockPayload[] = [
                    'sync_uuid' => $stock->sync_uuid ?? (string) Str::uuid(),
                    'product_id' => $stock->product_id,
                    'product_sync_uuid' => $pSyncUuid,
                    'type' => $stock->type,
                    'quantity' => (float) $stock->quantity,
                    'status' => $stock->status ?? 'completed',
                    'notes' => $stock->notes ?? null,
                ];
            }

            $categoriesPayload = [];
            foreach ($unsyncedCategories as $cat) {
                $categoriesPayload[] = [
                    'sync_uuid' => $cat->sync_uuid ?? (string) Str::uuid(),
                    'name' => $cat->name,
                    'slug' => $cat->slug ?? Str::slug($cat->name),
                    'sort_order' => (int) ($cat->sort_order ?? 0),
                    'is_active' => (bool) ($cat->is_active ?? true),
                ];
            }

            $productsPayload = [];
            foreach ($unsyncedProducts as $prod) {
                $categorySyncUuid = DB::connection('sqlite')->table('categories')->where('id', $prod->category_id)->value('sync_uuid');
                $productsPayload[] = [
                    'id' => $prod->id,
                    'sync_uuid' => $prod->sync_uuid ?? (string) Str::uuid(),
                    'category_id' => $prod->category_id,
                    'category_sync_uuid' => $categorySyncUuid,
                    'name' => $prod->name,
                    'slug' => $prod->slug ?? Str::slug($prod->name),
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

            $pushPayload = [
                'batch_id' => 'BATCH-'.time(),
                'checks' => $checksPayload,
                'check_items' => $checkItemsPayload,
                'payments' => $paymentsPayload,
                'stock_movements' => $stockPayload,
                'categories' => $categoriesPayload,
                'products' => $productsPayload,
                'deleted_products' => $deletedProducts,
                'deleted_categories' => $deletedCategories,
                'sync_resources' => $genericChanges['resources'],
                'deleted_resources' => $genericChanges['deleted_resources'],
            ];
            if ($restaurantCredentials !== null) {
                $pushUrl = config(
                    'services.adisyon.restaurant_push_url',
                    'https://adisyon.synaptropic.com/api/v1/sync/push/restaurant'
                );
                $response = Http::timeout(60)
                    ->acceptJson()
                    ->post($pushUrl, array_merge($pushPayload, $restaurantCredentials));
            } else {
                $pushUrl = config('services.adisyon.push_url', 'https://adisyon.synaptropic.com/api/v1/sync/push');
                $response = Http::timeout(60)->withHeaders([
                    'X-Device-Api-Key' => $apiKey,
                    'Accept' => 'application/json',
                ])->post($pushUrl, $pushPayload);
            }

            if ($response->successful() && $response->json('success')) {
                $syncedUuids = $response->json('synced_uuids') ?? [];

                if (! empty($syncedUuids)) {
                    DB::connection('sqlite')->table('checks')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => 1]);
                    DB::connection('sqlite')->table('check_items')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => 1]);
                    DB::connection('sqlite')->table('payments')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => 1]);
                    DB::connection('sqlite')->table('stock_movements')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => 1]);
                    DB::connection('sqlite')->table('categories')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => 1]);
                    DB::connection('sqlite')->table('products')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => 1]);

                    // Stok hareketi senkronize olan ürünlerin de is_synced durumunu 1 yap
                    $syncedStockProdIds = DB::connection('sqlite')->table('stock_movements')
                        ->whereIn('sync_uuid', $syncedUuids)
                        ->pluck('product_id')->filter()->toArray();
                    if (! empty($syncedStockProdIds)) {
                        DB::connection('sqlite')->table('products')->whereIn('id', $syncedStockProdIds)->update(['is_synced' => 1]);
                    }

                    if (Schema::connection('sqlite')->hasTable('deleted_records')) {
                        DB::connection('sqlite')->table('deleted_records')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => 1]);
                    }

                    app(BidirectionalSyncService::class)
                        ->markLocalChangesSynced($syncedUuids);
                }

                // ✅ PUSH başarılı olduktan sonra offline'da iptal edilen item'ları SQLite'dan tamamen kaldır
                // (MySQL'e iptal bilgisi aktarıldığı için artık local'de tutmaya gerek yok)
                DB::connection('sqlite')->table('check_items')
                    ->where('is_cancelled', true)
                    ->where('is_synced', true)
                    ->delete();

                $this->info('📤 Yerel çevrimdışı veriler ('.count($syncedUuids).' adet) canlı MySQL sunucusuna başarıyla PUSH edildi.');
                Log::channel('sync')->info('[SYNC-PUSH-SUCCESS] Yerel SQLite verileri canlı MySQL sunucusuna PUSH edildi.', [
                    'timestamp' => now()->toIso8601String(),
                    'synced_count' => count($syncedUuids),
                    'synced_uuids' => $syncedUuids,
                    'checks_count' => count($checksPayload),
                    'payments_count' => count($paymentsPayload),
                    'stock_count' => count($stockPayload),
                ]);

                return true;
            } else {
                $message = 'HTTP '.$response->status().': '.substr($response->body(), 0, 500);
                $this->warn('Çevrimdışı veri PUSH başarısız: '.$message);
                Log::channel('sync')->warning('[SYNC-PUSH-FAILED] Yerel SQLite verileri canlı sunucuya aktarılamadı.', [
                    'timestamp' => now()->toIso8601String(),
                    'status' => $response->status(),
                    'response_body' => substr($response->body(), 0, 500),
                ]);

                return false;
            }
        } catch (\Throwable $e) {
            $this->warn('Çevrimdışı veri PUSH uyarısı: '.$e->getMessage());
            Log::channel('sync')->warning('[SYNC-PUSH-WARN] Çevrimdışı PUSH aktarım uyarısı: '.$e->getMessage(), [
                'timestamp' => now()->toIso8601String(),
            ]);

            return false;
        }
    }
}
