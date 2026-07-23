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

        // Cihazı güncelle veya kaydet
        $device = Device::updateOrCreate(
            ['device_guid' => $deviceGuid],
            [
                'branch_id' => $license->branch_id,
                'license_id' => $license->id,
                'device_code' => $deviceCode,
                'ip_address' => $request->ip(),
                'os_info' => $validated['os_info'] ?? $request->header('User-Agent'),
                'status' => 'Online',
                'last_ping_at' => now(),
                'app_version' => $validated['app_version'] ?? '1.0.0',
            ]
        );

        // Cihaz yetki tokenı yoksa oluştur
        if (empty($license->device_token)) {
            $license->update(['device_token' => (string) Str::uuid()]);
        }

        // Log kaydı oluştur
        DeviceLog::create([
            'device_id' => $device->id,
            'event_type' => 'LicenseVerify',
            'ip_address' => $request->ip(),
            'request_payload' => $validated,
            'response_payload' => ['status' => 'Active', 'device_token' => $license->device_token],
        ]);

        return response()->json([
            'success' => true,
            'status' => 'Active',
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
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_guid' => 'required|string',
            'device_code' => 'nullable|string',
        ]);

        $device = Device::where('device_guid', $validated['device_guid'])->first();

        if ($device) {
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
        }

        return response()->json([
            'success' => true,
            'status' => 'OK',
            'server_time' => now()->toDateTimeString(),
        ]);
    }
}
