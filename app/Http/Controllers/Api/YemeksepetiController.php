<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Services\YemeksepetiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class YemeksepetiController extends Controller
{
    protected YemeksepetiService $service;

    public function __construct(YemeksepetiService $service)
    {
        $this->service = $service;
    }

    /**
     * Yemeksepeti Canlı Webhook Girişi (Canlı Sipariş Düştüğünde Çağrılır)
     * POST /api/v1/integrations/yemeksepeti/webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $rawBody = $request->getContent();
            $data = $request->all();

            Log::channel('sync')->info('[YEMEKSEPETI-WEBHOOK] Webhook İsteği Geldi:', [
                'ip' => $request->ip(),
                'data' => $data,
            ]);

            if (empty($data)) {
                $data = json_decode($rawBody, true) ?: [];
            }

            if (empty($data)) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Boş veya geçersiz JSON gövdesi',
                ], 400);
            }

            $orderData = $this->service->normalizeWebhookPayload($data);

            // Mükerrer Sipariş Kontrolü (Platform Order ID'ye Göre)
            $existing = DeliveryOrder::where('channel', 'yemeksepeti')
                ->where('platform_order_id', $orderData['platform_order_id'])
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'SUCCESS',
                    'message' => 'Sipariş zaten kayıtlı',
                    'order_id' => $existing->id,
                    'platform_order_id' => $existing->platform_order_id,
                ], 200);
            }

            // MySQL Sunucusuna Kaydet
            $order = DeliveryOrder::create($orderData);

            // Yerel SQLite Çevrimdışı Veritabanına Çift Yazma (Dual-Write)
            $this->syncOrderToSqlite($order);

            // Otomatik Onay Aktifse Yemeksepeti API'sine Onay Bildirimi Gönder
            if ($orderData['status'] === 'preparing') {
                $this->service->acceptOrder($order->platform_order_id);
            }

            Log::channel('sync')->info('[YEMEKSEPETI-WEBHOOK] Sipariş Başarıyla Oluşturuldu:', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer' => $order->customer_name,
                'total' => $order->total,
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Yemeksepeti siparişi başarıyla alındı ve kaydoldu',
                'order_id' => $order->id,
                'platform_order_id' => $order->platform_order_id,
                'order_number' => $order->order_number,
                'total' => $order->total,
            ], 201);

        } catch (\Throwable $e) {
            Log::channel('sync')->error('[YEMEKSEPETI-WEBHOOK] İstisna Hatası: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'ERROR',
                'message' => 'Sipariş işlenirken sunucu hatası oluştu: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Yemeksepeti Canlı Simülasyon Test Siparişi Tetikleyici
     * POST /api/v1/integrations/yemeksepeti/test-order
     */
    public function simulateTestOrder(Request $request): JsonResponse
    {
        try {
            $sampleNames = ['Deniz Aksoy', 'Caner Korkmaz', 'Selin Öztürk', 'Tolga Arslan'];
            $samplePhones = ['05334445566', '05425556677', '05546667788'];

            $orderNumber = '#' . rand(1000, 9999);
            $platformOrderId = 'YS-' . rand(100000, 999999);

            $payload = [
                'orderCode' => $platformOrderId,
                'orderNumber' => $orderNumber,
                'customer' => [
                    'firstName' => $sampleNames[array_rand($sampleNames)],
                    'lastName' => '',
                    'phone' => $samplePhones[array_rand($samplePhones)],
                    'deliveryAddress' => [
                        'address' => 'Moda Cad. Güneş Sok. No:' . rand(1, 100) . ' Kadıköy / İstanbul',
                        'note' => 'Kapıda ödeme. Zile basabilirsiniz.',
                    ],
                ],
                'payment' => [
                    'type' => 'POS_ON_DELIVERY',
                ],
                'courier' => [
                    'name' => 'Yemeksepeti Express Kuryesi',
                ],
                'items' => [
                    [
                        'name' => 'Yemeksepeti Süper Cheeseburger Menü',
                        'quantity' => 1,
                        'unitPrice' => 280.00,
                        'note' => 'Turşu olmasın',
                        'options' => [
                            ['name' => 'Büyük Boy Patates'],
                            ['name' => 'Kutu Kola Zero'],
                        ],
                    ],
                    [
                        'name' => 'Çıtır Çıtır Soğan Halkası (8 Adet)',
                        'quantity' => 1,
                        'unitPrice' => 85.00,
                    ],
                ],
                'pricing' => [
                    'subtotal' => 365.00,
                    'deliveryFee' => 12.00,
                    'discount' => 15.00,
                    'total' => 362.00,
                ],
            ];

            $simulatedRequest = Request::create('/api/v1/integrations/yemeksepeti/webhook', 'POST', [], [], [], [], json_encode($payload));
            $simulatedRequest->headers->set('Content-Type', 'application/json');

            return $this->handleWebhook($simulatedRequest);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Test siparişi oluşturulurken hata: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Yerel SQLite Veritabanına Çift Yazma (Dual-Write)
     */
    protected function syncOrderToSqlite(DeliveryOrder $order): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable('delivery_orders')) {
                DB::connection('sqlite')->table('delivery_orders')->updateOrInsert(
                    ['platform_order_id' => $order->platform_order_id],
                    [
                        'branch_id' => $order->branch_id ?? 1,
                        'channel' => 'yemeksepeti',
                        'platform_order_id' => $order->platform_order_id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'customer_phone' => $order->customer_phone,
                        'delivery_address' => $order->delivery_address,
                        'address_note' => $order->address_note,
                        'payment_method' => $order->payment_method,
                        'payment_status' => $order->payment_status,
                        'status' => $order->status,
                        'courier_type' => $order->courier_type,
                        'courier_name' => $order->courier_name,
                        'subtotal' => $order->subtotal,
                        'delivery_fee' => $order->delivery_fee,
                        'discount_total' => $order->discount_total,
                        'total' => $order->total,
                        'items' => json_encode($order->items),
                        'received_at' => $order->received_at ?? now(),
                        'created_at' => $order->created_at ?? now(),
                        'updated_at' => $order->updated_at ?? now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::channel('sync')->warning('[YEMEKSEPETI-SQLITE-SYNC] Yerel SQLite kayıt uyarısı: ' . $e->getMessage());
        }
    }
}
