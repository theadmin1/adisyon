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
    protected $signature = 'sync:local {--fresh : Yerel SQLite verilerini tamamen temizleyip canlı MySQL verilerini sıfırdan çeker}';

    protected $aliases = ['app:sync-local'];

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

            $apiUrl = config('services.adisyon.api_url', 'https://adisyon.synaptropic.com/api/v1/sync/pull');

            $response = Http::timeout(15)->withHeaders([
                'X-Device-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->get($apiUrl);

            if ($response->successful() && $response->json('success')) {
                $payload = $response->json('data');
                $tablesCount = count($payload['tables'] ?? []);
                $checksCount = count($payload['checks'] ?? []);
                $this->info("Çekilen Masalar: {$tablesCount} | Çekilen Adisyonlar: {$checksCount}");
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

            $this->error('Uzak sunucuya ulaşılamadı (Status: ' . ($response->status() ?? 'N/A') . '). Yanıt: ' . substr($response->body(), 0, 300));
            return Command::FAILURE;

        } catch (\Throwable $e) {
            $this->error('Senkronizasyon hatası: ' . $e->getMessage());
            Log::error('Yerel Veritabanı Senkronizasyon Hatası: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function syncDataToSqlite($users, $staff, $halls, $tables, $categories, $products, $checks): void
    {
        DB::connection('sqlite')->transaction(function () use ($users, $staff, $halls, $tables, $categories, $products, $checks) {
            // Users
            foreach ($users as $u) {
                $uArr = is_array($u) ? $u : json_decode(json_encode($u), true);
                if (isset($uArr['id'])) {
                    $matchKey = !empty($uArr['restaurant_id']) ? ['restaurant_id' => $uArr['restaurant_id']] : ['id' => $uArr['id']];
                    DB::connection('sqlite')->table('users')->updateOrInsert(
                        $matchKey,
                        [
                            'id' => $uArr['id'],
                            'name' => $uArr['name'] ?? '',
                            'email' => $uArr['email'] ?? '',
                            'password' => $uArr['password'] ?? '',
                            'is_admin' => $uArr['is_admin'] ?? false,
                            'restaurant_id' => $uArr['restaurant_id'] ?? null,
                            'created_at' => $uArr['created_at'] ?? now(),
                            'updated_at' => $uArr['updated_at'] ?? now(),
                        ]
                    );
                }
            }

            // Staff Profiles
            foreach ($staff as $s) {
                $sArr = is_array($s) ? $s : json_decode(json_encode($s), true);
                if (isset($sArr['id'])) {
                    DB::connection('sqlite')->table('staff_profiles')->updateOrInsert(
                        ['id' => $sArr['id']],
                        [
                            'branch_id' => $sArr['branch_id'] ?? 1,
                            'name' => $sArr['name'] ?? '',
                            'role' => $sArr['role'] ?? 'Garson',
                            'pin_code' => $sArr['pin_code'] ?? '0000',
                            'avatar_color' => $sArr['avatar_color'] ?? 'indigo',
                            'is_active' => $sArr['is_active'] ?? true,
                            'created_at' => $sArr['created_at'] ?? now(),
                            'updated_at' => $sArr['updated_at'] ?? now(),
                        ]
                    );
                }
            }

            // Halls
            foreach ($halls as $h) {
                $hArr = is_array($h) ? $h : json_decode(json_encode($h), true);
                if (isset($hArr['id'])) {
                    DB::connection('sqlite')->table('halls')->updateOrInsert(
                        ['id' => $hArr['id']],
                        [
                            'branch_id' => $hArr['branch_id'] ?? 1,
                            'name' => $hArr['name'] ?? '',
                            'code' => $hArr['code'] ?? '',
                            'sort_order' => $hArr['sort_order'] ?? 0,
                            'is_active' => $hArr['is_active'] ?? true,
                            'created_at' => $hArr['created_at'] ?? now(),
                            'updated_at' => $hArr['updated_at'] ?? now(),
                        ]
                    );
                }
            }

            // Tables
            foreach ($tables as $t) {
                $tArr = is_array($t) ? $t : json_decode(json_encode($t), true);
                if (isset($tArr['id'])) {
                    DB::connection('sqlite')->table('dining_tables')->updateOrInsert(
                        ['id' => $tArr['id']],
                        [
                            'branch_id' => $tArr['branch_id'] ?? 1,
                            'hall_id' => $tArr['hall_id'] ?? null,
                            'name' => $tArr['name'] ?? '',
                            'code' => $tArr['code'] ?? '',
                            'capacity' => $tArr['capacity'] ?? 4,
                            'occupant_count' => $tArr['occupant_count'] ?? 0,
                            'status' => $tArr['status'] ?? 'available',
                            'is_active' => $tArr['is_active'] ?? true,
                            'notes' => $tArr['notes'] ?? null,
                            'created_at' => $tArr['created_at'] ?? now(),
                            'updated_at' => $tArr['updated_at'] ?? now(),
                        ]
                    );
                }
            }

            // Categories
            foreach ($categories as $c) {
                $cArr = is_array($c) ? $c : json_decode(json_encode($c), true);
                if (isset($cArr['id'])) {
                    DB::connection('sqlite')->table('categories')->updateOrInsert(
                        ['id' => $cArr['id']],
                        [
                            'branch_id' => $cArr['branch_id'] ?? 1,
                            'name' => $cArr['name'] ?? '',
                            'sort_order' => $cArr['sort_order'] ?? 0,
                            'is_active' => $cArr['is_active'] ?? true,
                            'created_at' => $cArr['created_at'] ?? now(),
                            'updated_at' => $cArr['updated_at'] ?? now(),
                        ]
                    );
                }
            }

            // Products
            foreach ($products as $p) {
                $pArr = is_array($p) ? $p : json_decode(json_encode($p), true);
                if (isset($pArr['id'])) {
                    DB::connection('sqlite')->table('products')->updateOrInsert(
                        ['id' => $pArr['id']],
                        [
                            'branch_id' => $pArr['branch_id'] ?? 1,
                            'category_id' => $pArr['category_id'] ?? null,
                            'name' => $pArr['name'] ?? '',
                            'price' => $pArr['price'] ?? 0,
                            'stock_quantity' => $pArr['stock_quantity'] ?? 0,
                            'is_active' => $pArr['is_active'] ?? true,
                            'created_at' => $pArr['created_at'] ?? now(),
                            'updated_at' => $pArr['updated_at'] ?? now(),
                        ]
                    );
                }
            }

            // Checks & Check Items
            foreach ($checks as $c) {
                $cArr = is_array($c) ? $c : json_decode(json_encode($c), true);
                if (isset($cArr['id'])) {
                    DB::connection('sqlite')->table('checks')->updateOrInsert(
                        ['id' => $cArr['id']],
                        [
                            'branch_id' => $cArr['branch_id'] ?? 1,
                            'dining_table_id' => $cArr['dining_table_id'] ?? null,
                            'waiter_id' => $cArr['waiter_id'] ?? null,
                            'check_number' => $cArr['check_number'] ?? '',
                            'sync_uuid' => $cArr['sync_uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                            'is_synced' => true,
                            'guest_count' => $cArr['guest_count'] ?? 1,
                            'status' => $cArr['status'] ?? 'open',
                            'subtotal' => $cArr['subtotal'] ?? 0,
                            'discount_total' => $cArr['discount_total'] ?? 0,
                            'tax_total' => $cArr['tax_total'] ?? 0,
                            'total' => $cArr['total'] ?? 0,
                            'opened_at' => $cArr['opened_at'] ?? now(),
                            'closed_at' => $cArr['closed_at'] ?? null,
                            'kitchen_sent_at' => $cArr['kitchen_sent_at'] ?? null,
                            'created_at' => $cArr['created_at'] ?? now(),
                            'updated_at' => $cArr['updated_at'] ?? now(),
                        ]
                    );

                    $items = $cArr['items'] ?? [];
                    foreach ($items as $item) {
                        $iArr = is_array($item) ? $item : json_decode(json_encode($item), true);
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

            // SQLite masa durumlarını aktif açık adisyonlara göre tam senkronize et
            DB::connection('sqlite')->table('dining_tables')->update(['status' => 'available']);
            $activeTableIds = DB::connection('sqlite')->table('checks')
                ->whereIn('status', ['open', 'awaiting_payment'])
                ->whereNotNull('dining_table_id')
                ->pluck('dining_table_id')
                ->unique()
                ->toArray();

            if (!empty($activeTableIds)) {
                DB::connection('sqlite')->table('dining_tables')
                    ->whereIn('id', $activeTableIds)
                    ->update(['status' => 'occupied']);
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
            $unsyncedChecks = DB::connection('sqlite')->table('checks')->where('is_synced', false)->get();
            $unsyncedPayments = DB::connection('sqlite')->table('payments')->where('is_synced', false)->get();
            $unsyncedStockMovements = DB::connection('sqlite')->table('stock_movements')->where('is_synced', false)->get();

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
                    'user_id' => $check->user_id,
                    'staff_profile_id' => $check->waiter_id,
                    'total_amount' => (float) $check->total,
                    'discount_amount' => (float) $check->discount_total,
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
