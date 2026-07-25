<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.default' => 'sqlite']);

echo "🛠️ Lisanslar güncelleniyor..." . PHP_EOL;

try {
    \App\Models\License::query()->update([
        'status' => 'Active',
        'expires_at' => null,
        'max_devices' => 10,
    ]);
    echo "✅ SQLite lisansları Aktif (Active, 10 Cihaz) yapıldı." . PHP_EOL;
} catch (\Throwable $e) {
    echo "SQLite güncelleme hatası: " . $e->getMessage() . PHP_EOL;
}
