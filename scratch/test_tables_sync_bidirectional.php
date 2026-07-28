<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\DiningTable;
use App\Services\Checks\CheckService;

echo "=== TEST: OPENING A TABLE LOCALLY (SQLite) ===\n";
$table = DiningTable::find(2); // Masa 2
$statusStr = is_object($table->status) ? $table->status->value : $table->status;
echo "Table 2 BEFORE status: {$statusStr}\n";

$checkService = new CheckService();
$check = $checkService->openCheck($table);
echo "Check created: {$check->check_number} (sync_uuid: {$check->sync_uuid}) on Table 2\n";

$table->refresh();
$statusStr = is_object($table->status) ? $table->status->value : $table->status;
echo "Table 2 AFTER openCheck status: {$statusStr}\n";

echo "\n=== RUNNING SYNC (app:sync-local) ===\n";
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
echo \Illuminate\Support\Facades\Artisan::output();

$table->refresh();
$statusStr = is_object($table->status) ? $table->status->value : $table->status;
echo "Table 2 AFTER SYNC status in SQLite: {$statusStr}\n";

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

if ($r->successful()) {
    $remoteTables = collect($r->json('data.tables') ?? []);
    $remoteTable2 = $remoteTables->firstWhere('id', 2);
    echo "Table 2 in MySQL (Remote) status: " . ($remoteTable2['status'] ?? 'NOT FOUND') . "\n";
    
    $remoteChecks = collect($r->json('data.checks') ?? []);
    $remoteCheck2 = $remoteChecks->firstWhere('sync_uuid', $check->sync_uuid);
    echo "Check {$check->check_number} in MySQL (Remote): " . ($remoteCheck2 ? "FOUND (dining_table_id: {$remoteCheck2['dining_table_id']}, status: {$remoteCheck2['status']})" : "NOT FOUND") . "\n";
}
