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
        $this->info('🚀 Uzak veriler yerel SQLite veritabanına aktarılıyor...');

        try {
            // Eğer doğrudan uzak MySQL erişilebilirse, PDO kopyalama yap
            try {
                $remoteUsers = DB::connection('mysql')->table('users')->get();
                $remoteHalls = DB::connection('mysql')->table('halls')->get();
                $remoteTables = DB::connection('mysql')->table('dining_tables')->get();
                $remoteCategories = DB::connection('mysql')->table('categories')->get();
                $remoteProducts = DB::connection('mysql')->table('products')->get();
                $remoteChecks = DB::connection('mysql')->table('checks')->where('status', 'open')->get();

                $this->syncDataToSqlite($remoteUsers, $remoteHalls, $remoteTables, $remoteCategories, $remoteProducts, $remoteChecks);
                $this->info('✅ Uzak MySQL verileri başarıyla yerel SQLite veritabanına yazıldı.');
                return Command::SUCCESS;
            } catch (\Throwable $mysqlEx) {
                $this->warn('Uzak MySQL doğrudan erişilemedi. API uçları deneniyor...');
            }

            // HTTP API üzerinden veri çekme
            $apiUrl = config('services.adisyon.api_url', 'https://adisyon.synaptropic.com/api/v1/sync/pull');
            $apiKey = config('services.adisyon.device_api_key', '');

            $response = Http::timeout(5)->withHeaders([
                'X-Device-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->get($apiUrl);

            if ($response->successful() && $response->json('success')) {
                $payload = $response->json('data');
                $this->syncDataToSqlite(
                    collect($payload['users'] ?? []),
                    collect($payload['halls'] ?? []),
                    collect($payload['tables'] ?? []),
                    collect($payload['categories'] ?? []),
                    collect($payload['products'] ?? []),
                    collect($payload['checks'] ?? [])
                );
                $this->info('✅ Uzak API verileri başarıyla yerel SQLite veritabanına aktarıldı.');
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

    private function syncDataToSqlite($users, $halls, $tables, $categories, $products, $checks): void
    {
        DB::connection('sqlite')->transaction(function () use ($users, $halls, $tables, $categories, $products, $checks) {
            // Users
            foreach ($users as $u) {
                $uArr = (array) $u;
                DB::connection('sqlite')->table('users')->updateOrInsert(
                    ['id' => $uArr['id']],
                    [
                        'name' => $uArr['name'],
                        'email' => $uArr['email'],
                        'restaurant_id' => $uArr['restaurant_id'] ?? null,
                        'password' => $uArr['password'],
                        'role' => $uArr['role'] ?? 'staff',
                        'is_active' => $uArr['is_active'] ?? true,
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
        });
    }
}
