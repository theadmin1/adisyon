<?php

namespace App\Http\Controllers\Api\Waiter;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWaiterApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $token = $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE);
        $host = (string) config('broadcasting.connections.reverb.options.host');
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
            $host = $request->getHost();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => config('broadcasting.default') === 'reverb'
                    && (string) config('broadcasting.connections.reverb.key') !== '',
                'driver' => 'reverb',
                'protocol' => 'pusher',
                'app_key' => (string) config('broadcasting.connections.reverb.key'),
                'host' => $host,
                'port' => (int) config('broadcasting.connections.reverb.options.port', $request->isSecure() ? 443 : 80),
                'scheme' => (string) config('broadcasting.connections.reverb.options.scheme', $request->isSecure() ? 'https' : 'http'),
                'channel' => "private-waiter.branch.{$token->branch_id}",
                'event' => 'waiter.updated',
                'auth_endpoint' => url('/api/v1/waiter/realtime/auth'),
                'auth_headers' => ['Authorization' => 'Bearer {access_token}'],
                'reconnect' => true,
                'fallback_sync' => [
                    'orders' => '/api/v1/waiter/orders?updated_after={last_server_time}',
                    'kitchen' => '/api/v1/waiter/kitchen/updates?since={last_server_time}',
                    'tables' => '/api/v1/waiter/halls',
                    'menu' => '/api/v1/waiter/categories',
                ],
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    public function authenticate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:255'],
        ]);
        $token = $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE);
        $expectedChannel = "private-waiter.branch.{$token->branch_id}";
        abort_unless(hash_equals($expectedChannel, $validated['channel_name']), 403);

        $key = (string) config('broadcasting.connections.reverb.key');
        $secret = (string) config('broadcasting.connections.reverb.secret');
        abort_if($key === '' || $secret === '', 503, 'Realtime kimlik bilgileri yapılandırılmamış.');

        $signature = hash_hmac(
            'sha256',
            $validated['socket_id'].':'.$validated['channel_name'],
            $secret,
        );

        return response()->json(['auth' => $key.':'.$signature]);
    }
}
