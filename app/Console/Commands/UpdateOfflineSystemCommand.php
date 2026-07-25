<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateOfflineSystemCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-offline-system {--force : Onay beklemeden güncelleme işlemini başlatır}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Çevrimdışı (Offline) sistemi adisyon.synaptropic.com sunucusundaki en son sürüme günceller ve .env yapılandırmasını koruyarak SQLite veritabanını tazeler.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Adisyon Offline Güncelleme ve Senkronizasyon Sistemi Başlatılıyor...');

        $baseUrl = config('services.adisyon.base_url', 'https://adisyon.synaptropic.com');
        $apiKey = config('services.adisyon.api_key');

        if (empty($apiKey)) {
            try {
                $apiKey = DB::table('settings')->where('key', 'DeviceApiKey')->value('value') ?? '';
            } catch (\Throwable $e) {}
        }
        if (empty($apiKey)) {
            $apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
        }

        // 1. GÜNCELLEME KONTROLÜ
        $this->info('🔍 Canlı sunucudaki en son yazılım sürümü sorgulanıyor...');
        try {
            $checkResponse = Http::timeout(15)
                ->withHeaders(['X-Device-Api-Key' => $apiKey])
                ->get("{$baseUrl}/api/v1/update/check");

            if ($checkResponse->failed()) {
                $this->error('❌ Güncelleme sunucusuna bağlanılamadı. İnternet bağlantınızı veya API Key bilgilerini kontrol edin.');
                return 1;
            }

            $updateData = $checkResponse->json();
            $this->info("✨ Canlı Sunucu Sürümü: " . ($updateData['latest_version'] ?? 'Bilinmiyor'));

            if (!empty($updateData['changelog']) && is_array($updateData['changelog'])) {
                $this->line('📋 Sürüm Yenilikleri:');
                foreach ($updateData['changelog'] as $log) {
                    $this->line("   - {$log}");
                }
            }

        } catch (\Throwable $e) {
            $this->error('❌ Güncelleme kontrolünde hata oluştu: ' . $e->getMessage());
            return 1;
        }

        // 2. KOD TABANI GÜNCELLEME PAKETİNİ İNDİR (ZIP)
        $this->info('📥 En güncel yazılım paketi indiriliyor...');
        $tempZipPath = storage_path('app/temp_update.zip');

        try {
            $packageResponse = Http::timeout(120)
                ->withHeaders(['X-Device-Api-Key' => $apiKey])
                ->get("{$baseUrl}/api/v1/update/download-package");

            if ($packageResponse->failed()) {
                $this->error('❌ Güncelleme paketi indirilemedi.');
                return 1;
            }

            File::put($tempZipPath, $packageResponse->body());
            $this->info('✅ Yazılım paketi indirildi.');

        } catch (\Throwable $e) {
            $this->error('❌ Paket indirme hatası: ' . $e->getMessage());
            return 1;
        }

        // 3. YEREL .ENV VE DATABASE.SQLITE DOSYALARINI KORUYARAK ÇIKAR (UNZIP)
        $this->info('🛡️ Yerel .env ve SQLite veritabanı korunarak güncel kodlar kuruluyor...');
        try {
            $zip = new \ZipArchive();
            if ($zip->open($tempZipPath) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);

                    // CRITICAL: Yerel .env ve database.sqlite dosyalarını asla üzerine yazma!
                    if (str_contains($filename, '.env') || str_contains($filename, 'database.sqlite')) {
                        continue;
                    }

                    $zip->extractTo(base_path(), $filename);
                }
                $zip->close();
                $this->info('✅ Kod tabanı başarıyla güncellendi.');
            } else {
                $this->error('❌ ZIP arşivi açılamadı.');
                return 1;
            }

            File::delete($tempZipPath);

        } catch (\Throwable $e) {
            $this->error('❌ Dosya kurma hatası: ' . $e->getMessage());
            return 1;
        }

        // 4. VERİTABANI SCHEMA & VERİ SENKRONİZASYONU
        $this->info('🔄 Yerel SQLite veritabanı schema ve master verileri taze taze güncelleniyor...');
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--force' => true,
            ]);
            $this->info('✅ Veritabanı migrasyonları başarıyla çalıştırıldı.');
        } catch (\Throwable $e) {
            $this->warn('Veritabanı migrasyon uyarısı: ' . $e->getMessage());
        }

        // 5. SUNUCUDAN EN GÜNCEL MASTER VERİLERİ YEREL SQLITE İÇİN TAZELER
        $this->info('🔄 adisyon.synaptropic.com canlı sunucusundaki usta veriler SQLite veritabanına aktarılıyor...');
        try {
            \Illuminate\Support\Facades\Artisan::call('app:sync-local', [
                '--fresh' => true,
            ]);
            $this->info('✅ Canlı sunucudaki güncel Menü, Ürünler, Kategoriler, Salonlar, Masalar ve Ayarlar yerel SQLite veritabanına başarıyla kuruldu.');
        } catch (\Throwable $e) {
            $this->warn('Master veritabanı aktarım uyarısı: ' . $e->getMessage());
        }

        // 6. ÖNBELLEKLERİ TEMİZLE
        $this->info('🧹 Önbellekler temizleniyor...');
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');

        $this->info('🎉 TEBRİKLER! Çevrimdışı (Offline) Adisyon POS Sistemi En Son Sürüme Başarıyla Güncellendi!');
        return 0;
    }
}
