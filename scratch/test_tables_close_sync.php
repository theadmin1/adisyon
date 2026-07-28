<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\DiningTable;
use App\Services\Checks\CheckService;

echo "=== TEST: CLOSING CHECKS ON TABLE 2 LOCALLY (SQLite) ===\n";
$table = DiningTable::find(2); // Masa 2

$checkService = new CheckService();
$openChecks = $table->checks()->where('status', 'open')->get();
foreach ($openChecks as $c) {
    $checkService->closeCheck($c);
    echo "Closed Check: {$c->check_number}\n";
}

$table->refresh();
$statusStr = is_object($table->status) ? $table->status->value : $table->status;
echo "Table 2 AFTER closeCheck status: {$statusStr}\n";

echo "\n=== RUNNING SYNC (app:sync-local) ===\n";
\Illuminate\Support\Facades\Artisan::call('app:sync-local');

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
}
