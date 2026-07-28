<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StaffProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UpdateApiController extends Controller
{
    /**
     * Güncelleme ve offline sürüm bilgisini sorgular.
     */
    public function checkUpdate(Request $request): JsonResponse
    {
        $currentVersion = $request->query('version', '1.0.0');

        $latestVersion = '1.2.5';
        $releaseNotes = [
            'Paket Servis (Delivery) Konsolu yenilendi.',
            'Trendyol Go, Yemeksepeti, GetirYemek, Migros Yemek canlı entegrasyonları eklendi.',
            'Dinamik Otomatik Onay ve Mutfak Fişi Otomatik Yazdırma entegre edildi.',
            'Geçmiş Paket Siparişler Arşivi ve Z-Raporu / Kasa Özeti portalları eklendi.',
            'Sipariş Yaşlandırma Sayaçları ve Gecikme Uyarısı neon görselleştirmeleri eklendi.',
        ];

        return response()->json([
            'success' => true,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'has_update' => version_compare($latestVersion, $currentVersion, '>'),
            'release_date' => now()->toIso8601String(),
            'changelog' => $releaseNotes,
            'package_download_url' => route('api.update.download_package'),
            'database_download_url' => route('api.update.download_database'),
        ]);
    }

    /**
     * En son güncel yazılım paketini (ZIP) indirir.
     */
    public function downloadPackage(Request $request): BinaryFileResponse|JsonResponse
    {
        $backupDir = storage_path('app/updates');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $zipPath = $backupDir.'/adisyon_latest_release.zip';

        // ZIP paketini oluştur (Hassas .env ve database.sqlite HARİÇ)
        if (! File::exists($zipPath) || (time() - File::lastModified($zipPath) > 3600)) {
            $zip = new \ZipArchive;
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $foldersToInclude = ['app', 'config', 'database/migrations', 'database/seeders', 'public', 'resources', 'routes', 'bootstrap'];

                foreach ($foldersToInclude as $folder) {
                    $fullPath = base_path($folder);
                    if (File::isDirectory($fullPath)) {
                        $files = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($fullPath),
                            \RecursiveIteratorIterator::LEAVES_ONLY
                        );

                        foreach ($files as $file) {
                            if (! $file->isDir()) {
                                $filePath = $file->getRealPath();
                                $relativePath = substr($filePath, strlen(base_path()) + 1);
                                $relativePath = str_replace('\\', '/', $relativePath);
                                if (str_starts_with($relativePath, 'public/uploads/')
                                    || str_starts_with($relativePath, 'public/storage/')) {
                                    continue;
                                }
                                $zip->addFile($filePath, $relativePath);
                            }
                        }
                    } elseif (File::exists($fullPath)) {
                        $zip->addFile($fullPath, $folder);
                    }
                }
                $zip->close();
            }
        }

        if (File::exists($zipPath)) {
            $sha256 = hash_file('sha256', $zipPath);
            $signature = $this->signPackageHash($sha256);

            if ($signature === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Güncelleme imzalama anahtarı yapılandırılmamış.',
                ], 503);
            }

            return response()->download($zipPath, 'adisyon_software_update.zip', [
                'Content-Type' => 'application/zip',
                'X-Update-SHA256' => $sha256,
                'X-Update-Signature' => $signature,
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Güncelleme paketi oluşturulamadı.'], 500);
    }

    /**
     * Ana sunucudaki güncel veritabanı (Şube, Menü, Ürünler, Masalar, Personeller) verisini dışa aktarır.
     */
    public function downloadDatabaseSnapshot(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');
        $branchId = (int) $device->branch_id;

        $data = [
            'timestamp' => now()->toIso8601String(),
            'branch' => Branch::findOrFail($branchId),
            'categories' => Category::forBranch($branchId)->where('is_active', true)->get(),
            'products' => Product::forBranch($branchId)->where('is_active', true)->get(),
            'halls' => Hall::forBranch($branchId)->with([
                'diningTables' => fn ($query) => $query->forBranch($branchId),
            ])->get(),
            'dining_tables' => DiningTable::forBranch($branchId)->get(),
            'staff_profiles' => StaffProfile::forBranch($branchId)
                ->where('is_active', true)
                ->get(['id', 'name', 'role', 'avatar_color', 'is_active', 'branch_id']),
            'settings' => Setting::forBranch($branchId)
                ->whereNotIn('key', ['DeviceApiKey', 'RestaurantLoginPassword'])
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Güncel veritabanı verisi başarıyla aktarıldı.',
            'snapshot' => $data,
        ]);
    }

    private function signPackageHash(string $sha256): ?string
    {
        $privateKey = config('services.updates.signing_private_key');
        if (! is_string($privateKey) || trim($privateKey) === '') {
            return null;
        }

        $privateKey = str_replace('\n', "\n", trim($privateKey));
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            return null;
        }

        $signed = openssl_sign($sha256, $signature, $key, OPENSSL_ALGO_SHA256);

        return $signed ? base64_encode($signature) : null;
    }
}
