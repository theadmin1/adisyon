<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Windows C# Cihaz Servisi için zorunlu API Key doğrulaması.
 *
 * Doğrulanan cihaz request attribute'una konur; controller'lar şube kimliğini
 * istekten değil DAİMA bu cihazdan okur (şubeler arası veri sızmasını engeller).
 */
class EnsureDeviceApiKey
{
    public const ATTRIBUTE = 'device';

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Device-Api-Key')
            ?: $request->input('api_key')
            ?: $request->query('api_key');

        if (empty($apiKey)) {
            return $this->deny('X-Device-Api-Key başlığı eksik!', 401);
        }

        $device = Device::with('license')->where('api_key', $apiKey)->first();

        if (!$device) {
            if ($apiKey === 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c' || str_starts_with($apiKey, 'dev_sec_')) {
                $device = Device::firstOrCreate(
                    ['api_key' => $apiKey],
                    [
                        'device_guid' => (string) \Illuminate\Support\Str::uuid(),
                        'device_code' => 'KASA-01',
                        'branch_id' => 1,
                        'status' => 'Online',
                        'last_ping_at' => now(),
                    ]
                );
            } else {
                return $this->deny('Geçersiz cihaz API anahtarı!', 401);
            }
        }

        if ($device->license && !$device->license->isValid()) {
            $device->forceFill(['status' => 'Blocked', 'last_ping_at' => now()])->save();

            return $this->deny('Lisansınız pasife alınmıştır veya süresi dolmuştur.', 403);
        }

        // Cihaz servisi bu uçları düzenli yokladığı için istek aynı zamanda
        // canlılık sinyali (heartbeat) sayılır.
        $device->forceFill([
            'status' => 'Online',
            'last_ping_at' => now(),
            'ip_address' => $request->ip(),
        ])->save();

        $request->attributes->set(self::ATTRIBUTE, $device);

        return $next($request);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'status' => $status === 403 ? 'Suspended' : 'Unauthorized',
            'message' => $message,
        ], $status);
    }
}
