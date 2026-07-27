<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ✅ Çift yönlü senkronizasyon: Yerel SQLite modunda her dakika arka planda PUSH/PULL çalıştır.
// Bu sayede online tarafta yapılan değişiklikler (ürün ekleme/silme, masa kapatma vb.)
// kullanıcı işlem yapmasa bile periyodik olarak yerel terminale yansır.
if (config('database.default') === 'sqlite') {
    Schedule::command('app:sync-local')->everyMinute()->withoutOverlapping();
}
