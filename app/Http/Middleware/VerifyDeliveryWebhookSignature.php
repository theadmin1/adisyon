<?php

namespace App\Http\Middleware;

use App\Models\DeliveryIntegration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDeliveryWebhookSignature
{
    public function handle(Request $request, Closure $next, string $channel): Response
    {
        $payload = $request->json()->all();
        $storeId = $request->header('X-Store-Id')
            ?: data_get($payload, 'storeId')
            ?: data_get($payload, 'store.id')
            ?: data_get($payload, 'supplierId')
            ?: data_get($payload, 'vendorId')
            ?: data_get($payload, 'restaurantId');

        $integrations = DeliveryIntegration::withoutGlobalScopes()
            ->where('channel', $channel)
            ->where('is_active', true)
            ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
            ->limit(2)
            ->get();

        if ($integrations->count() !== 1) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Webhook mağaza kimliği eşleştirilemedi.',
            ], 422);
        }

        $secret = (string) config("services.delivery.webhook_secrets.{$channel}", '');

        if ($secret === '') {
            if (app()->environment(['local', 'testing']) && config('services.delivery.allow_unsigned_local')) {
                return $next($request);
            }

            return response()->json([
                'status' => 'ERROR',
                'message' => 'Webhook imza anahtarı yapılandırılmamış.',
            ], 503);
        }

        $provided = (string) (
            $request->header('X-Webhook-Signature')
            ?: $request->header('X-Signature')
        );
        $provided = str_starts_with($provided, 'sha256=') ? substr($provided, 7) : $provided;
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($provided === '' || ! hash_equals($expected, strtolower($provided))) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Webhook imzası geçersiz.',
            ], 401);
        }

        $request->attributes->set('delivery_integration', $integrations->first());

        return $next($request);
    }
}
