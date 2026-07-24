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
    protected $signature = 'app:sync-local';

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
            // Eğer doğrudan uzak MySQL erişilebilirse, PDO kopyalama yap
            try {
                $remoteUsers = DB::connection('mysql')->table('users')->get();
                $remoteStaff = DB::connection('mysql')->table('staff_profiles')->get();
                $remoteHalls = DB::connection('mysql')->table('halls')->get();
                $remoteTables = DB::connection('mysql')->table('dining_tables')->get();
                $remoteCategories = DB::connection('mysql')->table('categories')->get();
                $remoteProducts = DB::connection('mysql')->table('products')->get();
                $remoteChecks = DB::connection('mysql')->table('checks')->where('status', 'open')->get();

                $this->syncDataToSqlite($remoteUsers, $remoteStaff, $remoteHalls, $remoteTables, $remoteCategories, $remoteProducts, $remoteChecks);
                $this->info('✅ adisyon.synaptropic.com canlı verileri yerel çevrimdışı moda başarıyla yüklendi.');
                return Command::SUCCESS;
            } catch (\Throwable $mysqlEx) {
                $this->warn('Uzak MySQL doğrudan erişilemedi. API uçları deneniyor...');
            }

            // HTTP API üzerinden veri çekme
            $apiUrl = config('services.adisyon.api_url', 'https://adisyon.synaptropic.com/api/v1/sync/pull');
            $apiKey = config('services.adisyon.device_api_key', '');

            if (empty($apiKey)) {
                try {
                    $apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? '';
                } catch (\Throwable $e) {}
            }

            $response = Http::timeout(5)->withHeaders([
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
        DB::connection('sqlite')->transaction(function () use ($users, $staff, $halls, $tables, $categories, $products, $checks) {
            // Users
            foreach ($users as $u) {
                $uArr = (array) $u;
                DB::connection('sqlite')->table('users')->updateOrInsert(
                    ['id' => $uArr['id']],
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
                DB::connection('sqlite')->table('dining_tables')->updateOrInsert(
                    ['id' => $tArr['id']],
                    [
                        'hall_id' => $tArr['hall_id'],
                        'table_number' => $tArr['table_number'],
                        'capacity' => $tArr['capacity'] ?? 4,
                        'status' => $tArr['status'] ?? 'empty',
                    ]
                );
            }

            // Categories
            foreach ($categories as $c) {
                $cArr = (array) $c;
                DB::connection('sqlite')->table('categories')->updateOrInsert(
                    ['id' => $cArr['id']],
                    [
                        'name' => $cArr['name'],
                        'icon' => $cArr['icon'] ?? null,
                        'sort_order' => $cArr['sort_order'] ?? 0,
                    ]
                );
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
}
