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
            if (\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('users') && \App\Models\User::on('sqlite')->count() === 0) {
                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                    '--database' => 'sqlite',
                    '--force' => true,
                ]);
            }
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

            // 1. ÖNCE: Çevrimdışı modda yerelde oluşan henüz senkronize olmamış adisyon, ödeme ve stok hareketlerini canlı sunucuya PUSH et!
            $this->pushUnsyncedLocalDataToCloud($apiKey);

            // 2. SONRA: Canlı HTTPS API üzerinden güncel verileri PULL et!
            $apiUrl = config('services.adisyon.api_url', 'https://adisyon.synaptropic.com/api/v1/sync/pull');

            $response = Http::timeout(30)->withHeaders([
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
                    collect($payload['checks'] ?? [])
                );
                $this->info('✅ adisyon.synaptropic.com canlı verileri yerel çevrimdışı moda başarıyla yüklendi.');
                return Command::SUCCESS;
            }

            $this->error('Uzak sunucuya ulaşılamadı. Yerel veritabanı mevcut haliyle kullanılacak.');
            return Command::FAILURE;

        } catch (\Throwable $e) {
            $this->error('Senkronizasyon hatası: ' . $e->getMessage());
            Log::error('Yerel Veritabanı Senkronizasyon Hatası: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function syncDataToSqlite($users, $staff, $halls, $tables, $categories, $products, $checks): void
    {
        DB::connection('sqlite')->statement('PRAGMA foreign_keys = OFF;');

        DB::connection('sqlite')->transaction(function () use ($users, $staff, $halls, $tables, $categories, $products, $checks) {
            // Branches
            DB::connection('sqlite')->table('branches')->updateOrInsert(
                ['id' => 1],
                ['name' => 'Merkez Şube', 'created_at' => now(), 'updated_at' => now()]
            );

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

            // Categories
            foreach ($categories as $c) {
                $cArr = (array) $c;
                if (isset($cArr['id'])) {
                    DB::connection('sqlite')->table('categories')->updateOrInsert(
                        ['id' => $cArr['id']],
                        [
                            'name' => $cArr['name'] ?? 'Kategori',
                            'slug' => $cArr['slug'] ?? null,
                            'sort_order' => $cArr['sort_order'] ?? 0,
                            'is_active' => $cArr['is_active'] ?? true,
                        ]
                    );
                }
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

            // Products
            foreach ($products as $p) {
                $pArr = (array) $p;
                DB::connection('sqlite')->table('products')->updateOrInsert(
                    ['id' => $pArr['id']],
                    [
                        'category_id' => $pArr['category_id'],
                        'name' => $pArr['name'],
                        'price' => $pArr['price'],
                        'is_active' => $pArr['is_active'] ?? true,
                        'stock_quantity' => $pArr['stock_quantity'] ?? 100,
                    ]
                );
            }

            // Open Checks & Items
            foreach ($checks as $chk) {
                $cArr = (array) $chk;
                if (isset($cArr['id'])) {
                    DB::connection('sqlite')->table('checks')->updateOrInsert(
                        ['id' => $cArr['id']],
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
                        ]
                    );

                    $items = $cArr['items'] ?? [];
                    foreach ($items as $item) {
                        $iArr = (array) $item;
                        if (isset($iArr['id'])) {
                            DB::connection('sqlite')->table('check_items')->updateOrInsert(
                                ['id' => $iArr['id']],
                                [
                                    'check_id' => $cArr['id'],
                                    'product_id' => $iArr['product_id'] ?? null,
                                    'product_name' => $iArr['product_name'] ?? 'Ürün',
                                    'sync_uuid' => $iArr['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                                    'is_synced' => true,
                                    'unit_price' => $iArr['unit_price'] ?? 0,
                                    'quantity' => $iArr['quantity'] ?? 1,
                                    'total_price' => $iArr['total_price'] ?? 0,
                                    'notes' => $iArr['notes'] ?? null,
                                    'is_complimentary' => $iArr['is_complimentary'] ?? false,
                                    'is_cancelled' => $iArr['is_cancelled'] ?? false,
                                ]
                            );
                        }
                    }
                }
            }
        });
    }

    /**
     * Çevrimdışı modda yerel SQLite veritabanında oluşan henüz senkronize edilmemiş (is_synced = 0)
     * adisyon, kalem, ödeme ve stok hareketlerini canlı MySQL sunucusuna PUSH eder.
     */
    private function pushUnsyncedLocalDataToCloud(string $apiKey): void
    {
        try {
            $unsyncedChecks = DB::connection('sqlite')->table('checks')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
            $unsyncedPayments = DB::connection('sqlite')->table('payments')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();
            $unsyncedStockMovements = DB::connection('sqlite')->table('stock_movements')->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))->get();

            if ($unsyncedChecks->isEmpty() && $unsyncedPayments->isEmpty() && $unsyncedStockMovements->isEmpty()) {
                return;
            }

            $checksPayload = [];
            foreach ($unsyncedChecks as $check) {
                $items = DB::connection('sqlite')->table('check_items')->where('check_id', $check->id)->get();
                $itemsPayload = [];
                foreach ($items as $item) {
                    $itemsPayload[] = [
                        'sync_uuid' => $item->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name ?? 'Ürün',
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => (int) $item->quantity,
                        'total_price' => (float) $item->total_price,
                        'status' => $item->kitchen_status ?? 'pending',
                    ];
                }

                $checksPayload[] = [
                    'sync_uuid' => $check->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
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
                    'quantity' => (int) $stock->quantity,
                ];
            }

            $pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
            $response = Http::timeout(10)->withHeaders([
                'X-Device-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->post($pushUrl, [
                'batch_id' => 'BATCH-' . time(),
                'checks' => $checksPayload,
                'payments' => $paymentsPayload,
                'stock_movements' => $stockPayload,
            ]);

            if ($response->successful() && $response->json('success')) {
                $syncedUuids = $response->json('synced_uuids') ?? [];

                if (!empty($syncedUuids)) {
                    DB::connection('sqlite')->table('checks')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
                    DB::connection('sqlite')->table('payments')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
                    DB::connection('sqlite')->table('stock_movements')->whereIn('sync_uuid', $syncedUuids)->update(['is_synced' => true]);
                }

                $this->info('📤 Yerel çevrimdışı veriler (' . count($syncedUuids) . ' adet) canlı MySQL sunucusuna başarıyla PUSH edildi.');
            }
        } catch (\Throwable $e) {
            $this->warn('Çevrimdışı veri PUSH uyarısı: ' . $e->getMessage());
        }
    }
}
