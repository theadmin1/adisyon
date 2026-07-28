<?php

namespace App\Services;

use App\Models\DeliveryIntegration;
use App\Models\DeliveryOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YemeksepetiService
{
    protected string $baseUrl;

    protected ?string $vendorId;

    protected ?string $apiKey;

    protected ?string $apiSecret;

    protected bool $isActive;

    protected bool $autoAccept;

    public function __construct(?DeliveryIntegration $integration = null)
    {
        if (! $integration) {
            $integration = DeliveryIntegration::where('channel', 'yemeksepeti')->first();
        }

        $this->vendorId = $integration?->store_id ?: env('YEMEKSEPETI_VENDOR_ID');
        $this->apiKey = $integration?->api_key ?: env('YEMEKSEPETI_API_KEY');
        $this->apiSecret = $integration?->api_secret ?: env('YEMEKSEPETI_API_SECRET');
        $this->isActive = $integration?->is_active ?? false;
        $this->autoAccept = $integration?->auto_accept ?? false;

        $envMode = env('YEMEKSEPETI_ENV', 'stage'); // stage or prod
        $this->baseUrl = $envMode === 'prod'
            ? 'https://pos-api.yemeksepeti.com/v1/vendors/'.$this->vendorId
            : 'https://sandbox-api.deliveryhero.com/v1/vendors/'.$this->vendorId;
    }

    /**
     * Yemeksepeti (Delivery Hero) Webhook Payload'unu DeliveryOrder Formatına Dönüştürür
     */
    public function normalizeWebhookPayload(array $data): array
    {
        $orderCode = $data['orderCode'] ?? $data['id'] ?? $data['order_id'] ?? ('YS-'.rand(100000, 999999));
        $orderNumber = $data['orderNumber'] ?? $data['code'] ?? ('#'.rand(1000, 9999));

        $customer = $data['customer'] ?? [];
        $customerName = trim(($customer['firstName'] ?? '').' '.($customer['lastName'] ?? ''));
        if (empty($customerName)) {
            $customerName = $data['customerName'] ?? 'Yemeksepeti Müşterisi';
        }

        $customerPhone = $customer['phone'] ?? $customer['mobilePhone'] ?? $data['customerPhone'] ?? '05551112233';

        $addressObj = $customer['deliveryAddress'] ?? $customer['address'] ?? $data['deliveryAddress'] ?? [];
        $deliveryAddress = is_array($addressObj)
            ? ($addressObj['address'] ?? $addressObj['fullAddress'] ?? ($addressObj['street'] ?? 'Atatürk Cad.').', '.($addressObj['city'] ?? 'İstanbul'))
            : (string) $addressObj;
        $addressNote = is_array($addressObj) ? ($addressObj['note'] ?? $addressObj['addressNote'] ?? '') : ($data['addressNote'] ?? '');

        // Ödeme Yöntemi
        $paymentTypeRaw = strtoupper($data['payment']['type'] ?? $data['paymentMethod'] ?? 'ONLINE');
        $paymentMethod = match ($paymentTypeRaw) {
            'CASH', 'NAKIT', 'CASH_ON_DELIVERY' => 'cash_on_delivery',
            'CREDIT_CARD', 'POS', 'POS_ON_DELIVERY' => 'pos_on_delivery',
            default => 'online',
        };

        // Kalemler
        $rawItems = $data['items'] ?? $data['products'] ?? [];
        $items = [];
        $calculatedSubtotal = 0;

        foreach ($rawItems as $raw) {
            $name = $raw['name'] ?? $raw['productName'] ?? 'Yemeksepeti Menü';
            $qty = (int) ($raw['quantity'] ?? $raw['count'] ?? 1);
            $price = (float) ($raw['unitPrice'] ?? $raw['price'] ?? 0);
            $itemTotal = $price * $qty;
            $calculatedSubtotal += $itemTotal;

            $optionsText = [];
            if (! empty($raw['options']) && is_array($raw['options'])) {
                foreach ($raw['options'] as $opt) {
                    $optName = is_array($opt) ? ($opt['name'] ?? '') : (string) $opt;
                    if ($optName) {
                        $optionsText[] = $optName;
                    }
                }
            }

            $items[] = [
                'name' => $name,
                'quantity' => $qty,
                'price' => $price,
                'total' => $itemTotal,
                'note' => $raw['note'] ?? $raw['itemNote'] ?? null,
                'options' => ! empty($optionsText) ? implode(', ', $optionsText) : null,
            ];
        }

        $pricing = $data['pricing'] ?? $data['price'] ?? [];
        $subtotal = (float) ($pricing['subtotal'] ?? $pricing['subTotal'] ?? $calculatedSubtotal);
        $deliveryFee = (float) ($pricing['deliveryFee'] ?? $pricing['delivery_fee'] ?? 12.00);
        $discountTotal = (float) ($pricing['discount'] ?? $pricing['discountTotal'] ?? 0.00);
        $total = (float) ($pricing['total'] ?? ($subtotal + $deliveryFee - $discountTotal));

        $courierName = $data['courier']['name'] ?? 'Yemeksepeti Kuryesi';

        return [
            'channel' => 'yemeksepeti',
            'platform_order_id' => (string) $orderCode,
            'order_number' => (string) $orderNumber,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'delivery_address' => $deliveryAddress,
            'address_note' => $addressNote,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentMethod === 'online' ? 'paid' : 'pending',
            'status' => $this->autoAccept ? 'preparing' : 'new',
            'courier_type' => 'platform',
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
     * Siparişi Yemeksepeti API Üzerinde Onayla (Confirm / Accept)
     */
    public function acceptOrder(string $platformOrderId): bool
    {
        try {
            $endpoint = $this->baseUrl.'/orders/'.$platformOrderId.'/confirm';
            $response = Http::withOptions([])
                ->timeout(10)
                ->withHeaders($this->getHeaders())
                ->post($endpoint, [
                    'status' => 'ACCEPTED',
                    'acceptedAt' => now()->toIso8601String(),
                ]);

            Log::channel('sync')->info('[YEMEKSEPETI-API] Order Accept Response', [
                'platform_order_id' => $platformOrderId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::channel('sync')->error('[YEMEKSEPETI-API] Order Accept Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Kuryeye Teslim Et / Yola Çıkar (Dispatch)
     */
    public function dispatchOrder(string $platformOrderId): bool
    {
        try {
            $endpoint = $this->baseUrl.'/orders/'.$platformOrderId.'/dispatch';
            $response = Http::withOptions([])
                ->timeout(10)
                ->withHeaders($this->getHeaders())
                ->post($endpoint);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::channel('sync')->error('[YEMEKSEPETI-API] Dispatch Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Siparişi İptal Et (Cancel Order)
     */
    public function cancelOrder(string $platformOrderId, string $reason = 'RESTAURANT_BUSY'): bool
    {
        try {
            $endpoint = $this->baseUrl.'/orders/'.$platformOrderId.'/cancel';
            $response = Http::withOptions([])
                ->timeout(10)
                ->withHeaders($this->getHeaders())
                ->post($endpoint, [
                    'reason' => $reason,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::channel('sync')->error('[YEMEKSEPETI-API] Cancel Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Yemeksepeti (Delivery Hero) API Header Yapısı
     */
    protected function getHeaders(): array
    {
        $authStr = base64_encode($this->apiKey.':'.$this->apiSecret);

        return [
            'Authorization' => 'Basic '.$authStr,
            'x-vendor-id' => $this->vendorId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Adisyon-Yemeksepeti-Integration/1.0',
        ];
    }
}
