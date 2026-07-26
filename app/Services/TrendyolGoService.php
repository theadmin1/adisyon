<?php

namespace App\Services;

use App\Models\DeliveryIntegration;
use App\Models\DeliveryOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrendyolGoService
{
    protected string $baseUrl;
    protected ?string $supplierId;
    protected ?string $apiKey;
    protected ?string $apiSecret;
    protected bool $isActive;
    protected bool $autoAccept;

    public function __construct(?DeliveryIntegration $integration = null)
    {
        if (!$integration) {
            $integration = DeliveryIntegration::where('channel', 'trendyol')->first();
        }

        $this->supplierId = $integration?->store_id ?: env('TRENDYOL_GO_SUPPLIER_ID', '1098412');
        $this->apiKey = $integration?->api_key ?: env('TRENDYOL_GO_API_KEY', 'ty_go_key_demo');
        $this->apiSecret = $integration?->api_secret ?: env('TRENDYOL_GO_API_SECRET', 'ty_go_sec_demo');
        $this->isActive = $integration?->is_active ?? true;
        $this->autoAccept = $integration?->auto_accept ?? false;

        $envMode = env('TRENDYOL_GO_ENV', 'stage'); // stage or prod
        $this->baseUrl = $envMode === 'prod' 
            ? 'https://api.tgoapps.com/suppliers/' . $this->supplierId
            : 'https://stageapi.tgoapps.com/suppliers/' . $this->supplierId;
    }

    /**
     * Trendyol Go Webhook Payload'unu DeliveryOrder Formatına Dönüştürür
     */
    public function normalizeWebhookPayload(array $data): array
    {
        $orderId = $data['orderId'] ?? $data['packageId'] ?? $data['id'] ?? ('TYG-' . rand(100000, 999999));
        $orderNumber = $data['orderNumber'] ?? ('#' . rand(1000, 9999));
        
        $customer = $data['customer'] ?? [];
        $customerName = $customer['name'] ?? $data['customerName'] ?? 'Trendyol Müşterisi';
        $customerPhone = $customer['phone'] ?? $customer['maskPhone'] ?? $data['customerPhone'] ?? '05550000000';
        
        $addressObj = $customer['address'] ?? $data['address'] ?? [];
        $deliveryAddress = is_array($addressObj) 
            ? ($addressObj['fullAddress'] ?? $addressObj['addressLine'] ?? ($addressObj['district'] ?? 'Kadıköy') . ', ' . ($addressObj['city'] ?? 'İstanbul'))
            : (string) $addressObj;
        $addressNote = is_array($addressObj) ? ($addressObj['addressNote'] ?? $data['addressNote'] ?? '') : ($data['addressNote'] ?? '');

        // Ödeme Yöntemi
        $paymentTypeRaw = strtoupper($data['payment']['type'] ?? $data['paymentMethod'] ?? 'ONLINE');
        $paymentMethod = match($paymentTypeRaw) {
            'CASH_ON_DELIVERY', 'CASH', 'NAKIT' => 'cash_on_delivery',
            'POS_ON_DELIVERY', 'CREDIT_CARD_ON_DELIVERY', 'POS' => 'pos_on_delivery',
            default => 'online',
        };

        // Kalemler
        $rawItems = $data['items'] ?? $data['orderItems'] ?? [];
        $items = [];
        $calculatedSubtotal = 0;

        foreach ($rawItems as $raw) {
            $name = $raw['name'] ?? $raw['productName'] ?? 'Trendyol Ürün';
            $qty = (int) ($raw['quantity'] ?? $raw['count'] ?? 1);
            $price = (float) ($raw['price'] ?? $raw['unitPrice'] ?? 0);
            $itemTotal = $price * $qty;
            $calculatedSubtotal += $itemTotal;

            $optionsText = [];
            if (!empty($raw['options']) && is_array($raw['options'])) {
                foreach ($raw['options'] as $opt) {
                    $optName = is_array($opt) ? ($opt['name'] ?? '') : (string)$opt;
                    if ($optName) $optionsText[] = $optName;
                }
            }

            $items[] = [
                'name' => $name,
                'quantity' => $qty,
                'price' => $price,
                'total' => $itemTotal,
                'note' => $raw['note'] ?? $raw['itemNote'] ?? null,
                'options' => !empty($optionsText) ? implode(', ', $optionsText) : null,
            ];
        }

        $pricing = $data['pricing'] ?? $data['price'] ?? [];
        $subtotal = (float) ($pricing['subTotal'] ?? $pricing['subtotal'] ?? $calculatedSubtotal);
        $deliveryFee = (float) ($pricing['deliveryFee'] ?? $pricing['delivery_fee'] ?? 15.00);
        $discountTotal = (float) ($pricing['discount'] ?? $pricing['discountTotal'] ?? 0.00);
        $total = (float) ($pricing['total'] ?? ($subtotal + $deliveryFee - $discountTotal));

        $courierType = strtoupper($data['courier']['type'] ?? $data['courierType'] ?? 'TRENDYOL_EXPRESS');
        $courierName = $data['courier']['name'] ?? ($courierType === 'TRENDYOL_EXPRESS' ? 'Trendyol Go Kuryesi' : 'Restoran Kuryesi');

        return [
            'channel' => 'trendyol',
            'platform_order_id' => (string) $orderId,
            'order_number' => (string) $orderNumber,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'delivery_address' => $deliveryAddress,
            'address_note' => $addressNote,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentMethod === 'online' ? 'paid' : 'pending',
            'status' => $this->autoAccept ? 'preparing' : 'new',
            'courier_type' => $courierType,
            'courier_name' => $courierName,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'discount_total' => $discountTotal,
            'total' => $total,
            'items' => $items,
            'received_at' => now(),
            'accepted_at' => $this->autoAccept ? now() : null,
        ];
    }

    /**
     * Siparişi Trendyol Go API Üzerinde Onayla (Prepare / Accept)
     */
    public function acceptOrder(string $platformOrderId): bool
    {
        try {
            $endpoint = $this->baseUrl . '/orders/' . $platformOrderId . '/prepare';
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->withHeaders($this->getHeaders())
                ->put($endpoint);

            Log::channel('sync')->info('[TRENDYOL-GO-API] Order Accept Response', [
                'platform_order_id' => $platformOrderId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::channel('sync')->error('[TRENDYOL-GO-API] Order Accept Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Kuryeye Teslim Et (Picked Up / Dispatched)
     */
    public function handoverToCourier(string $platformOrderId): bool
    {
        try {
            $endpoint = $this->baseUrl . '/orders/' . $platformOrderId . '/picked-up';
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->withHeaders($this->getHeaders())
                ->put($endpoint);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::channel('sync')->error('[TRENDYOL-GO-API] Handover Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Siparişi İptal Et (Cancel Order)
     */
    public function cancelOrder(string $platformOrderId, string $reasonCode = 'OUT_OF_STOCK', string $comment = ''): bool
    {
        try {
            $endpoint = $this->baseUrl . '/orders/' . $platformOrderId . '/cancel';
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->withHeaders($this->getHeaders())
                ->post($endpoint, [
                    'reasonCode' => $reasonCode,
                    'comment' => $comment ?: 'Restoran yoğunluğu nedeniyle iptal edildi.',
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::channel('sync')->error('[TRENDYOL-GO-API] Cancel Order Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Trendyol Go Stage Sunucusundan Gerçek API Test Siparişi Oluşturma İsteği
     * POST https://stageapi.tgoapps.com/suppliers/{supplierId}/orders/test
     */
    public function createStageTestOrder(): array
    {
        try {
            $endpoint = $this->baseUrl . '/orders/test';
            $response = Http::withoutVerifying()
                ->timeout(12)
                ->withHeaders($this->getHeaders())
                ->post($endpoint, [
                    'customerName' => 'Trendyol Go Stage Müşterisi',
                    'customerPhone' => '05329998877',
                    'address' => 'Trendyol Go Stage Test Adresi Kadıköy / İstanbul',
                    'items' => [
                        [
                            'productId' => 'P-101',
                            'name' => 'Trendyol Go Canlı Stage Pizza',
                            'quantity' => 1,
                            'price' => 320.00,
                        ],
                    ],
                ]);

            Log::channel('sync')->info('[TRENDYOL-GO-STAGE-TEST] Stage API Response:', [
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Trendyol Go Stage sunucusundan gerçek test siparişi tetiklendi!',
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Stage API HTTP ' . $response->status() . ' döndü: ' . substr($response->body(), 0, 150),
                'data' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::channel('sync')->error('[TRENDYOL-GO-STAGE-TEST] Stage API Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Stage API sunucusuna bağlanılamadı: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Trendyol Go API İstemci Header Yapısı
     */
    protected function getHeaders(): array
    {
        $authStr = base64_encode($this->apiKey . ':' . $this->apiSecret);
        return [
            'Authorization' => 'Basic ' . $authStr,
            'x-api-key' => $this->apiKey,
            'x-supplier-id' => $this->supplierId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Adisyon-TrendyolGo-Integration/1.0',
        ];
    }
}
