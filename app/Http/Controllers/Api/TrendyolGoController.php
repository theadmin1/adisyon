<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryIntegration;
use App\Models\DeliveryOrder;
use App\Services\TrendyolGoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TrendyolGoController extends Controller
{
    protected TrendyolGoService $service;

    public function __construct(TrendyolGoService $service)
    {
        $this->service = $service;
    }

    /**
     * Trendyol Go Canlı Webhook Girişi (Canlı Sipariş Düştüğünde Çağrılır)
     * POST /api/v1/integrations/trendyol-go/webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $rawBody = $request->getContent();
            $data = $request->all();

            Log::channel('sync')->info('[TRENDYOL-GO-WEBHOOK] Webhook İsteği Geldi:', [
                'ip' => $request->ip(),
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

            $integration = $request->attributes->get('delivery_integration');
            abort_unless($integration, 422, 'Webhook mağazası eşleştirilemedi.');
            $service = new TrendyolGoService($integration);
            $orderData = $service->normalizeWebhookPayload($data);
            $orderData['branch_id'] = $integration->branch_id;

            // Mükerrer Sipariş Kontrolü (Platform Order ID'ye Göre)
            $existing = DeliveryOrder::forBranch($integration->branch_id)
                ->where('channel', 'trendyol')
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

            // Otomatik Onay Aktifse Trendyol Go API'sine Onay Bildirimi Gönder
            if ($orderData['status'] === 'preparing') {
                $service->acceptOrder($order->platform_order_id);
            }

            Log::channel('sync')->info('[TRENDYOL-GO-WEBHOOK] Sipariş Başarıyla Oluşturuldu:', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Trendyol Go siparişi başarıyla alındı ve kaydoldu',
                'order_id' => $order->id,
                'platform_order_id' => $order->platform_order_id,
                'order_number' => $order->order_number,
                'total' => $order->total,
            ], 201);

        } catch (\Throwable $e) {
            Log::channel('sync')->error('[TRENDYOL-GO-WEBHOOK] İstisna Hatası: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'ERROR',
                'message' => 'Sipariş işlenirken sunucu hatası oluştu.',
            ], 500);
        }
    }

    /**
     * Trendyol Go Canlı Simülasyon Test Siparişi Tetikleyici
     * POST /api/v1/integrations/trendyol-go/test-order
     */
    public function simulateTestOrder(Request $request): JsonResponse
    {
        try {
            $sampleNames = ['Ahmet Yılmaz', 'Mehmet Demir', 'Ayşe Kaya', 'Fatma Şahin', 'Burak Can'];
            $samplePhones = ['05321112233', '05432223344', '05553334455', '05054445566'];
            $sampleNotes = ['Zil çalmayın lütfen, bebek uyuyor.', 'Ketçap ve mayonez bol olsun.', 'Sos ayrı kapta gelsin.', 'Kapıya bırakıp zile basın.'];

            $orderNumber = '#'.rand(1000, 9999);
            $platformOrderId = 'TYG-'.rand(100000, 999999);

            $payload = [
                'orderId' => $platformOrderId,
                'orderNumber' => $orderNumber,
                'customer' => [
                    'name' => $sampleNames[array_rand($sampleNames)],
                    'phone' => $samplePhones[array_rand($samplePhones)],
                    'address' => [
                        'fullAddress' => 'Bağdat Caddesi No:'.rand(10, 250).' Daire:'.rand(1, 12).', Kadıköy / İstanbul',
                        'addressNote' => $sampleNotes[array_rand($sampleNotes)],
                        'district' => 'Kadıköy',
                        'city' => 'İstanbul',
                    ],
                ],
                'payment' => [
                    'type' => rand(0, 1) ? 'ONLINE' : 'POS_ON_DELIVERY',
                ],
                'courier' => [
                    'type' => 'TRENDYOL_EXPRESS',
                    'name' => 'Trendyol Express Kuryesi',
                ],
                'items' => [
                    [
                        'name' => 'Karışık Özel Pizza (Büyük Boy)',
                        'quantity' => 1,
                        'price' => 310.00,
                        'note' => 'Mısır olmasın lütfen',
                        'options' => [
                            ['name' => 'İnce Hamur'],
                            ['name' => 'Ekstra Peynir'],
                        ],
                    ],
                    [
                        'name' => 'Coca-Cola Zero Sugar 330ml',
                        'quantity' => 2,
                        'price' => 45.00,
                        'note' => 'Soğuk olsun',
                    ],
                    [
                        'name' => 'Fıstıklı Baklava (3 Dilim)',
                        'quantity' => 1,
                        'price' => 160.00,
                    ],
                ],
                'pricing' => [
                    'subTotal' => 560.00,
                    'deliveryFee' => 15.00,
                    'discount' => 25.00,
                    'total' => 550.00,
                ],
            ];

            // Webhook İşleyiciye Gönder
            $simulatedRequest = Request::create('/api/v1/integrations/trendyol-go/webhook', 'POST', [], [], [], [], json_encode($payload));
            $simulatedRequest->headers->set('Content-Type', 'application/json');
            $integration = DeliveryIntegration::forBranch((int) $request->user()->branch_id)
                ->where('channel', 'trendyol')
                ->firstOrFail();
            $simulatedRequest->attributes->set('delivery_integration', $integration);

            return $this->handleWebhook($simulatedRequest);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Test siparişi oluşturulurken hata: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Yerel SQLite Veritabanına Çift Yazma (Dual-Write)
     */
    protected function syncOrderToSqlite(DeliveryOrder $order): void
    {
        try {
            if (Schema::connection('sqlite')->hasTable('delivery_orders')) {
                DB::connection('sqlite')->table('delivery_orders')->updateOrInsert(
                    ['platform_order_id' => $order->platform_order_id],
                    [
                        'branch_id' => $order->branch_id,
                        'channel' => 'trendyol',
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
            Log::channel('sync')->warning('[TRENDYOL-GO-SQLITE-SYNC] Yerel SQLite kayıt uyarısı: '.$e->getMessage());
        }
    }
}
