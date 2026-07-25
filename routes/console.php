<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sync:local {--fresh}', function () {
    $this->call(App\Console\Commands\SyncLocalDatabaseCommand::class, [
        '--fresh' => $this->option('fresh')
    ]);
})->purpose('Uzak MySQL sunucusundaki verileri yerel SQLite veritabanına senkronize eder.');
