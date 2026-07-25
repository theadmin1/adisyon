<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$syncUuid = (string) Str::uuid();
$itemSyncUuid = (string) Str::uuid();

echo "1. Creating new offline order in local SQLite...\n";
$checkId = DB::connection('sqlite')->table('checks')->insertGetId([
    'branch_id' => 1,
    'dining_table_id' => 2, // Masa 2
    'waiter_id' => 115, // Antigravity Merkez Şube Yöneticisi
    'check_number' => 'CHK-TEST-' . rand(1000, 9999),
    'guest_count' => 2,
    'status' => 'open',
    'is_synced' => false,
    'sync_uuid' => $syncUuid,
    'subtotal' => 290.00,
    'discount_total' => 0.00,
    'total' => 290.00,
    'opened_at' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);

DB::connection('sqlite')->table('check_items')->insert([
    'check_id' => $checkId,
    'product_id' => 3, // Quattro Formaggi
    'product_name' => 'Quattro Formaggi',
    'unit_price' => 290.00,
    'quantity' => 1,
    'total_price' => 290.00,
    'kitchen_status' => 'pending',
    'is_synced' => false,
    'sync_uuid' => $itemSyncUuid,
    'created_at' => now(),
    'updated_at' => now(),
]);

// Update local dining table status
DB::connection('sqlite')->table('dining_tables')->where('id', 2)->update(['status' => 'occupied']);

echo "Local check created (ID: {$checkId}, Sync UUID: {$syncUuid})\n";

echo "2. Running php artisan app:sync-local to PUSH offline data...\n";
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
echo \Illuminate\Support\Facades\Artisan::output();

$localCheck = DB::connection('sqlite')->table('checks')->where('id', $checkId)->first();
echo "Local check is_synced status after PUSH: " . ($localCheck->is_synced ? "YES (Synced!)" : "NO") . "\n";
