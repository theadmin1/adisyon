<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AutoSyncService;
use App\Models\Product;

echo "=== DIRECT AUTOSYNC TEST ===\n";

$basePath = base_path();
echo "BasePath: {$basePath}\n";
echo "DB Default: " . config('database.default') . "\n";

$cmd = "cmd /c \"cd /d \"{$basePath}\" && php artisan app:sync-local\"";
echo "Executing CMD: {$cmd}\n\n";

$out = shell_exec($cmd);
echo "Output from shell_exec:\n{$out}\n";
