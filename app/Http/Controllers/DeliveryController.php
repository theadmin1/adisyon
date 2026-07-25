<?php

namespace App\Http\Controllers;

use App\Models\DeliveryIntegration;
use App\Models\DeliveryOrder;
use App\Models\Product;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * Display the Paket Servis POS Console.
     */
    public function index(Request $request)
    {
        $channelFilter = $request->query('channel', 'all');
        $statusFilter = $request->query('status', 'all');

        // Canlı sunucuda migration çalışmamışsa otomatik çalıştır
        if (!\Illuminate\Support\Facades\Schema::hasTable('delivery_orders') || !\Illuminate\Support\Facades\Schema::hasTable('delivery_integrations')) {
            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Delivery migration auto-trigger exception: ' . $e->getMessage());
            }
        }

        $defaultChannels = [
            'trendyol' => ['name' => 'Trendyol Go', 'color' => 'orange', 'icon' => 'fi-rr-shopping-bag'],
            'yemeksepeti' => ['name' => 'Yemeksepeti', 'color' => 'pink', 'icon' => 'fi-rr-utensils'],
            'getir' => ['name' => 'GetirYemek', 'color' => 'purple', 'icon' => 'fi-rr-motorcycle'],
            'migros' => ['name' => 'Migros Yemek', 'color' => 'amber', 'icon' => 'fi-rr-shopping-cart'],
        ];

        try {
            $query = DeliveryOrder::query()->latest();

            if ($channelFilter !== 'all') {
                $query->where('channel', $channelFilter);
            }

            if ($statusFilter !== 'all') {
                $query->where('status', $statusFilter);
            }

            $orders = $query->get();

            // Get or initialize default integration states
            $integrations = DeliveryIntegration::all()->keyBy('channel');

            foreach ($defaultChannels as $key => $meta) {
                if (!$integrations->has($key)) {
                    $integrations[$key] = DeliveryIntegration::create([
                        'channel' => $key,
                        'store_name' => $meta['name'] . ' Restoran',
                        'store_id' => strtoupper($key) . '-8842',
                        'api_key' => '',
                        'is_active' => true,
                        'auto_accept' => false,
                    ]);
                }
            }

            $stats = [
                'total_today' => DeliveryOrder::whereDate('created_at', now()->today())->count(),
                'new_count' => DeliveryOrder::where('status', 'new')->count(),
                'preparing_count' => DeliveryOrder::where('status', 'preparing')->count(),
                'on_the_way_count' => DeliveryOrder::where('status', 'on_the_way')->count(),
                'delivered_count' => DeliveryOrder::where('status', 'delivered')->whereDate('created_at', now()->today())->count(),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Delivery index error: ' . $e->getMessage());
            $orders = collect();
            $integrations = collect();
            $stats = [
                'total_today' => 0,
                'new_count' => 0,
                'preparing_count' => 0,
                'on_the_way_count' => 0,
                'delivered_count' => 0,
            ];
        }

        try {
            $products = Product::where('is_active', true)->get();
        } catch (\Throwable $e) {
            $products = collect();
        }

        return view('delivery.index', compact('orders', 'integrations', 'defaultChannels', 'products', 'stats', 'channelFilter', 'statusFilter'));
    }

    /**
     * Store a new phone delivery order.
     */
    public function storePhoneOrder(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'delivery_address' => 'required|string',
            'address_note' => 'nullable|string|max:255',
            'payment_method' => 'required|string|in:online,cash_on_delivery,pos_on_delivery',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required|numeric',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string',
        ]);

        $orderNumber = 'TEL-' . strtoupper(substr(uniqid(), -6));
        $subtotal = collect($validated['items'])->sum(fn($i) => $i['price'] * $i['quantity']);

        $order = DeliveryOrder::create([
            'channel' => 'phone',
            'order_number' => $orderNumber,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'delivery_address' => $validated['delivery_address'],
            'address_note' => $validated['address_note'] ?? null,
            'payment_method' => $validated['payment_method'],
            'payment_status' => $validated['payment_method'] === 'online' ? 'paid' : 'pending',
            'status' => 'preparing', // Direct phone orders start as preparing
            'subtotal' => $subtotal,
            'delivery_fee' => 0.00,
            'total' => $subtotal,
            'items' => $validated['items'],
            'received_at' => now(),
            'accepted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Telefon siparişi başarıyla kaydoldu.',
            'order' => $order,
        ]);
    }

    /**
     * Update delivery order status.
     */
    public function updateStatus(Request $request, DeliveryOrder $order)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:new,preparing,on_the_way,delivered,cancelled',
            'courier_name' => 'nullable|string|max:255',
            'courier_phone' => 'nullable|string|max:50',
            'cancellation_reason' => 'nullable|string|max:255',
        ]);

        $order->status = $validated['status'];

        if ($validated['status'] === 'preparing' && !$order->accepted_at) {
            $order->accepted_at = now();
        }

        if ($validated['status'] === 'on_the_way') {
            $order->dispatched_at = now();
            if (!empty($validated['courier_name'])) {
                $order->courier_name = $validated['courier_name'];
            }
            if (!empty($validated['courier_phone'])) {
                $order->courier_phone = $validated['courier_phone'];
            }
        }

        if ($validated['status'] === 'delivered') {
            $order->delivered_at = now();
            $order->payment_status = 'paid';
        }

        if ($validated['status'] === 'cancelled') {
            $order->cancelled_at = now();
            $order->cancellation_reason = $validated['cancellation_reason'] ?? 'İşletme Tarafından İptal Edildi';
        }

        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Sipariş durumu güncellendi.',
            'order' => $order,
        ]);
    }

    /**
     * Update integration credentials & toggles.
     */
    public function updateIntegrations(Request $request)
    {
        $validated = $request->validate([
            'integrations' => 'required|array',
            'integrations.*.channel' => 'required|string',
            'integrations.*.store_name' => 'nullable|string',
            'integrations.*.store_id' => 'nullable|string',
            'integrations.*.api_key' => 'nullable|string',
            'integrations.*.is_active' => 'required|boolean',
            'integrations.*.auto_accept' => 'required|boolean',
        ]);

        foreach ($validated['integrations'] as $data) {
            DeliveryIntegration::updateOrCreate(
                ['channel' => $data['channel']],
                [
                    'store_name' => $data['store_name'] ?? null,
                    'store_id' => $data['store_id'] ?? null,
                    'api_key' => $data['api_key'] ?? null,
                    'is_active' => $data['is_active'],
                    'auto_accept' => $data['auto_accept'],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Kanal entegrasyon ayarları başarıyla kaydedildi.',
        ]);
    }

    /**
     * Toggle status of integration channels.
     */
    public function toggleChannelStatus(Request $request)
    {
        $channel = $request->input('channel');
        $isActive = $request->boolean('is_active');

        if ($channel === 'all') {
            DeliveryIntegration::query()->update(['is_active' => $isActive]);
            return response()->json([
                'success' => true,
                'message' => 'Tüm platform kanalları ' . ($isActive ? 'açıldı' : 'kapatıldı'),
            ]);
        }

        $integration = DeliveryIntegration::where('channel', $channel)->first();
        if ($integration) {
            $integration->update(['is_active' => $isActive]);
            return response()->json([
                'success' => true,
                'message' => ucfirst($channel) . ' kanalı ' . ($isActive ? 'açıldı' : 'kapatıldı'),
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Kanal bulunamadı'], 404);
    }

    /**
     * Simulate an incoming platform order for live demo/testing.
     */
    public function simulateOrder(Request $request)
    {
        $channel = $request->input('channel', 'trendyol');

        $sampleNames = ['Ahmet Yılmaz', 'Ayşe Demir', 'Mehmet Kaya', 'Zeynep Çelik', 'Canan Öztürk', 'Emre Şahin'];
        $sampleAddresses = [
            'Atatürk Cad. Lale Sok. No: 12 Daire: 4 Kadıköy / İstanbul',
            'Gül Mah. Karanfil Sk. B Blok Kat: 3 Beşiktaş / İstanbul',
            'İnönü Bulvarı Mercan Sit. No: 45 Üsküdar / İstanbul',
            'Bağdat Cad. Yaprak Apt. No: 112 Maltepe / İstanbul',
        ];

        $sampleItemsPool = [
            ['product_id' => 1, 'name' => 'Burger Menü (Patates + İçecek)', 'price' => 245.00, 'quantity' => 1, 'note' => 'Soğansız olsun lütfen.'],
            ['product_id' => 2, 'name' => 'Adana Kebap Porsiyon', 'price' => 290.00, 'quantity' => 1, 'note' => 'Bol acılı ve lavaşlı.'],
            ['product_id' => 3, 'name' => 'Kutu Kola 330ml', 'price' => 45.00, 'quantity' => 2, 'note' => 'Soğuk gelsin.'],
            ['product_id' => 4, 'name' => 'Karışık Pizza (Orta Boy)', 'price' => 220.00, 'quantity' => 1, 'note' => 'Kurutulmuş domatesli.'],
            ['product_id' => 5, 'name' => 'Künefe Şerbetli', 'price' => 140.00, 'quantity' => 1, 'note' => 'Fıstığı bol olsun.'],
        ];

        // Pick 2 random items
        $selectedItems = collect($sampleItemsPool)->random(rand(1, 3))->values()->toArray();
        $subtotal = collect($selectedItems)->sum(fn($i) => $i['price'] * $i['quantity']);

        $channelPrefixes = [
            'trendyol' => 'TRN',
            'yemeksepeti' => 'YS',
            'getir' => 'GTR',
            'migros' => 'MGR',
        ];

        $prefix = $channelPrefixes[$channel] ?? 'ORD';
        $orderNumber = $prefix . '-' . rand(100000, 999999);
        $platformOrderId = '#' . strtoupper(substr(md5(microtime()), 0, 8));

        // Check if channel integration auto-accept is enabled
        $integration = DeliveryIntegration::where('channel', $channel)->first();
        $autoAccept = $integration ? $integration->auto_accept : false;

        $order = DeliveryOrder::create([
            'channel' => $channel,
            'platform_order_id' => $platformOrderId,
            'order_number' => $orderNumber,
            'customer_name' => $sampleNames[array_rand($sampleNames)],
            'customer_phone' => '05' . rand(30, 55) . ' ' . rand(100, 999) . ' ' . rand(10, 99) . ' ' . rand(10, 99),
            'delivery_address' => $sampleAddresses[array_rand($sampleAddresses)],
            'address_note' => 'Zile basmayın, kapıya bırakabilirsiniz.',
            'payment_method' => 'online',
            'payment_status' => 'paid',
            'status' => $autoAccept ? 'preparing' : 'new',
            'courier_type' => 'platform',
            'courier_name' => $channel === 'trendyol' ? 'Trendyol Express Kurye' : ucfirst($channel) . ' Kuryesi',
            'subtotal' => $subtotal,
            'delivery_fee' => 15.00,
            'total' => $subtotal + 15.00,
            'items' => $selectedItems,
            'received_at' => now(),
            'accepted_at' => $autoAccept ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => strtoupper($channel) . ' üzerinden yeni sipariş simüle edildi!',
            'order' => $order,
        ]);
    }

    /**
     * Clear test delivery orders.
     */
    public function clearTestOrders(Request $request)
    {
        DeliveryOrder::query()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tüm test siparişleri başarıyla temizlendi.',
        ]);
    }
}
