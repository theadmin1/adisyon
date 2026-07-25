<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.default' => 'sqlite']);

echo "=== LİSANSLAR (SQLite) ===" . PHP_EOL;
try {
    foreach (\App\Models\License::on('sqlite')->get() as $l) {
        echo "ID: {$l->id} | Key: {$l->license_key} | Status: {$l->status} | ValidTo: {$l->valid_to} | MaxDev: {$l->max_devices}" . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "Hata: " . $e->getMessage() . PHP_EOL;
}

echo "=== CİHAZLAR (SQLite) ===" . PHP_EOL;
try {
    foreach (\App\Models\Device::on('sqlite')->get() as $d) {
        echo "ID: {$d->id} | LicenseID: {$d->license_id} | Code: {$d->device_code} | GUID: {$d->device_guid} | Status: {$d->status}" . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "Hata: " . $e->getMessage() . PHP_EOL;
}
