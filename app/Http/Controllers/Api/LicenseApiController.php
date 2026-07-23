<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LicenseApiController extends Controller
{
    /**
     * Cihaz tarafından gönderilen Lisans Anahtarını ve Cihaz Kimliğini doğrular.
     * Başarılı doğrulamada cihaza özel Güvenli API Key (dev_sec_...) üretir ve döner.
     */
    public function verifyLicense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string',
            'device_guid' => 'required|string',
            'device_code' => 'nullable|string',
            'app_version' => 'nullable|string',
            'os_info' => 'nullable|string',
        ]);

        $licenseKey = trim($validated['license_key']);
        $deviceGuid = trim($validated['device_guid']);
        $deviceCode = $validated['device_code'] ?? 'KASA-01';

        $license = License::with('branch')->where('license_key', $licenseKey)->first();

        if (!$license) {
            return response()->json([
                'success' => false,
                'status' => 'InvalidKey',
                'message' => 'Geçersiz lisans anahtarı!',
            ], 404);
        }

        if (!$license->isValid()) {
            return response()->json([
                'success' => false,
                'status' => $license->status === 'Expired' ? 'Expired' : 'Suspended',
                'message' => "Lisans aktif değil veya süresi dolmuş (Durum: {$license->status})",
            ], 403);
        }

        // Cihazı arayalım veya oluşturalım
        $device = Device::where('device_guid', $deviceGuid)->first();

        // Güvenli Cihaz API Key üretimi (yoksa)
        $apiKey = $device?->api_key ?? ('dev_sec_' . Str::random(40));

        $device = Device::updateOrCreate(
            ['device_guid' => $deviceGuid],
            [
                'branch_id' => $license->branch_id,
                'license_id' => $license->id,
                'device_code' => $deviceCode,
                'api_key' => $apiKey,
                'ip_address' => $request->ip(),
                'os_info' => $validated['os_info'] ?? $request->header('User-Agent'),
                'status' => 'Online',
                'last_ping_at' => now(),
                'app_version' => $validated['app_version'] ?? '1.0.0',
            ]
        );

        // Lisansa device_token ata (yoksa)
        if (empty($license->device_token)) {
            $license->update(['device_token' => (string) Str::uuid()]);
        }

        // Log kaydı oluştur
        DeviceLog::create([
            'device_id' => $device->id,
            'event_type' => 'LicenseVerify',
            'ip_address' => $request->ip(),
            'request_payload' => $validated,
            'response_payload' => [
                'status' => 'Active',
                'api_key' => $apiKey,
                'device_token' => $license->device_token
            ],
        ]);

        return response()->json([
            'success' => true,
            'status' => 'Active',
            'api_key' => $apiKey, // 🔑 Cihazın sonraki tüm API isteklerinde kullanacağı yetki anahtarı
            'branch_name' => $license->branch ? $license->branch->name : 'Genel Şube',
            'device_token' => $license->device_token,
            'expires_at' => $license->expires_at ? $license->expires_at->toDateTimeString() : null,
            'restrictions' => $license->restrictions ?? [
                'disable_dev_tools' => true,
                'disable_context_menu' => true,
                'enable_kiosk_full_screen' => true,
                'hide_navigation_controls' => true,
                'restrict_allowed_domains' => true,
                'allowed_domains' => ['adisyon.synaptropic.com', '127.0.0.1', 'localhost'],
            ],
        ]);
    }

    /**
     * Cihazların periyodik olarak canlılık bildirdiği Heartbeat / Ping servisi.
     * X-Device-Api-Key başlığı veya api_key parametresi ile doğrulanır.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_guid' => 'required|string',
            'device_code' => 'nullable|string',
        ]);

        $apiKey = $request->header('X-Device-Api-Key') ?? $request->input('api_key');

        $query = Device::where('device_guid', $validated['device_guid']);
        if ($apiKey) {
            $query->where('api_key', $apiKey);
        }

        $device = $query->first();

        if ($device) {
            // Cihazın bağlı olduğu lisansın aktifliğini kontrol edelim
            if ($device->license && !$device->license->isValid()) {
                $device->update([
                    'status' => 'Blocked',
                    'last_ping_at' => now(),
                    'ip_address' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'status' => 'Suspended',
                    'is_license_valid' => false,
                    'message' => 'Lisansınız pasife alınmıştır veya süresi dolmuştur.',
                ], 403);
            }

            $device->update([
                'status' => 'Online',
                'last_ping_at' => now(),
                'ip_address' => $request->ip(),
            ]);

            DeviceLog::create([
                'device_id' => $device->id,
                'event_type' => 'HeartbeatPing',
                'ip_address' => $request->ip(),
                'request_payload' => $validated,
                'response_payload' => ['status' => 'OK'],
            ]);

            return response()->json([
                'success' => true,
                'status' => 'OK',
                'server_time' => now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'success' => false,
            'status' => 'Unauthorized',
            'message' => 'Geçersiz Cihaz Kimliği veya API Key!',
        ], 401);
    }
}
