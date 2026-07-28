<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LicenseApiController extends Controller
{
    public function verifyLicense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string|max:128',
            'device_guid' => 'required|uuid',
            'device_code' => 'nullable|string|max:100',
            'app_version' => 'nullable|string|max:50',
            'os_info' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated, $request): JsonResponse {
            $license = License::with('branch')
                ->where('license_key', trim($validated['license_key']))
                ->lockForUpdate()
                ->first();

            if (! $license || ! $license->isValid() || ! $license->branch?->is_active) {
                return response()->json([
                    'success' => false,
                    'status' => 'Invalid',
                    'message' => 'Lisans anahtarı geçersiz, pasif veya süresi dolmuş.',
                ], 403);
            }

            $deviceGuid = trim($validated['device_guid']);
            $device = Device::where('device_guid', $deviceGuid)->first();

            if ($device && $device->license_id !== $license->id) {
                return response()->json([
                    'success' => false,
                    'status' => 'DeviceMismatch',
                    'message' => 'Bu cihaz başka bir lisansa bağlı.',
                ], 409);
            }

            if (! $device && $license->devices()->count() >= (int) $license->max_devices) {
                return response()->json([
                    'success' => false,
                    'status' => 'DeviceLimit',
                    'message' => 'Lisansın cihaz limiti dolmuş.',
                ], 409);
            }

            $apiKey = 'dev_sec_'.Str::random(64);
            $device = Device::updateOrCreate(
                ['device_guid' => $deviceGuid],
                [
                    'branch_id' => $license->branch_id,
                    'license_id' => $license->id,
                    'device_code' => $validated['device_code'] ?? 'KASA-01',
                    'api_key' => null,
                    'api_key_hash' => hash('sha256', $apiKey),
                    'ip_address' => $request->ip(),
                    'os_info' => $validated['os_info'] ?? $request->userAgent(),
                    'status' => 'Online',
                    'last_ping_at' => now(),
                    'app_version' => $validated['app_version'] ?? '1.0.0',
                ]
            );

            if (empty($license->device_token)) {
                $license->update(['device_token' => (string) Str::uuid()]);
            }

            DeviceLog::create([
                'device_id' => $device->id,
                'event_type' => 'LicenseVerify',
                'ip_address' => $request->ip(),
                'request_payload' => [
                    'device_guid' => $deviceGuid,
                    'device_code' => $device->device_code,
                    'app_version' => $device->app_version,
                ],
                'response_payload' => ['status' => 'Active'],
            ]);

            return response()->json([
                'success' => true,
                'status' => 'Active',
                'api_key' => $apiKey,
                'branch_name' => $license->branch->name,
                'device_token' => $license->device_token,
                'expires_at' => $license->expires_at?->toDateTimeString(),
                'restrictions' => $license->restrictions ?? [
                    'disable_dev_tools' => true,
                    'disable_context_menu' => true,
                    'enable_kiosk_full_screen' => true,
                    'hide_navigation_controls' => true,
                    'restrict_allowed_domains' => true,
                    'allowed_domains' => ['adisyon.synaptropic.com', '127.0.0.1', 'localhost'],
                ],
            ]);
        });
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_guid' => 'required|uuid',
            'device_code' => 'nullable|string|max:100',
        ]);

        $apiKey = $request->header('X-Device-Api-Key');

        if (! is_string($apiKey) || $apiKey === '') {
            return response()->json([
                'success' => false,
                'status' => 'Unauthorized',
                'message' => 'X-Device-Api-Key başlığı eksik.',
            ], 401);
        }

        $device = Device::with(['license', 'branch'])
            ->where('device_guid', $validated['device_guid'])
            ->where('api_key_hash', hash('sha256', $apiKey))
            ->first();

        if (! $device || ! $device->branch?->is_active) {
            return response()->json([
                'success' => false,
                'status' => 'Unauthorized',
                'message' => 'Geçersiz cihaz kimliği veya API anahtarı.',
            ], 401);
        }

        if (! $device->license || ! $device->license->isValid()) {
            $device->update([
                'status' => 'Blocked',
                'last_ping_at' => now(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'Suspended',
                'is_license_valid' => false,
                'message' => 'Lisans pasif veya süresi dolmuş.',
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
            'request_payload' => ['device_guid' => $validated['device_guid']],
            'response_payload' => ['status' => 'OK'],
        ]);

        return response()->json([
            'success' => true,
            'status' => 'OK',
            'server_time' => now()->toDateTimeString(),
        ]);
    }
}
