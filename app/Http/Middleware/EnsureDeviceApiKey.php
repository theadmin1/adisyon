<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceApiKey
{
    public const ATTRIBUTE = 'device';

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Device-Api-Key');

        if (! is_string($apiKey) || $apiKey === '') {
            return $this->deny('X-Device-Api-Key başlığı eksik.', 401);
        }

        $device = Device::with(['license', 'branch'])
            ->where('api_key_hash', hash('sha256', $apiKey))
            ->first();

        if (! $device) {
            return $this->deny('Geçersiz cihaz API anahtarı.', 401);
        }

        if (! $device->branch || ! $device->branch->is_active) {
            return $this->deny('Cihazın bağlı olduğu şube aktif değil.', 403);
        }

        if (! $device->license || ! $device->license->isValid()) {
            $device->forceFill(['status' => 'Blocked', 'last_ping_at' => now()])->save();

            return $this->deny('Lisans pasif veya süresi dolmuş.', 403);
        }

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
