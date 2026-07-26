<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');

\App\Models\DeliveryIntegration::on('sqlite')->updateOrCreate(
    ['channel' => 'trendyol'],
    [
        'store_name' => 'Trendyol Go Lezzet Restoranı',
        'store_id' => '1098412',
        'api_key' => 'ty_go_key_demo',
        'api_secret' => 'ty_go_sec_demo',
        'is_active' => true,
        'auto_accept' => true,
    ]
);

echo "✅ Trendyol Go ayarları başarıyla kaydedildi!" . PHP_EOL;
