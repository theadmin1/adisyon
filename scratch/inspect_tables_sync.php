<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

echo "=== LOCAL SQLITE TABLES ===\n";
$sqliteTables = DB::connection('sqlite')->table('dining_tables')->get();
foreach ($sqliteTables as $t) {
    echo "ID: {$t->id} | Name: {$t->name} | HallID: {$t->hall_id} | Status: {$t->status}\n";
}

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

echo "\n=== REMOTE MYSQL TABLES ===\n";
if ($r->successful()) {
    $remoteTables = $r->json('data.tables') ?? [];
    foreach ($remoteTables as $t) {
        $tArr = (array)$t;
        echo "ID: " . ($tArr['id'] ?? 'null') . " | Name: " . ($tArr['name'] ?? 'null') . " | HallID: " . ($tArr['hall_id'] ?? 'null') . " | Status: " . ($tArr['status'] ?? 'null') . "\n";
    }
}
