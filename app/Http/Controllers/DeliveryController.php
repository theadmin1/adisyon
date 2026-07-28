<?php

namespace App\Http\Controllers;

use App\Models\DeliveryIntegration;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

            // Eksik kanalları yalnızca görünüm için varsayılan nesne olarak hazırla.
            $integrations = DeliveryIntegration::all()->keyBy('channel');

            foreach ($defaultChannels as $key => $meta) {
                if (! $integrations->has($key)) {
                    $integrations[$key] = new DeliveryIntegration([
                        'channel' => $key,
                        'store_name' => $meta['name'].' Restoran',
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

            $isAutoAccept = DeliveryIntegration::where('auto_accept', true)->exists();
            if (! $isAutoAccept) {
                try {
                    $setting = Setting::where('key', 'delivery_global_auto_accept')->first();
                    $isAutoAccept = $setting && $setting->value === '1';
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
            Log::error('Delivery index error: '.$e->getMessage());
            $orders = collect();
            $integrations = collect();
            $isAutoAccept = false;
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

        return view('delivery.index', compact('orders', 'integrations', 'defaultChannels', 'products', 'stats', 'channelFilter', 'statusFilter', 'isAutoAccept'));
    }

    /**
     * Display the Past Delivery Orders History & Archive.
     */
    public function history(Request $request)
    {
        $period = $request->query('period', 'today');
        $channelFilter = $request->query('channel', 'all');
        $statusFilter = $request->query('status', 'all');
        $searchQuery = trim($request->query('search', ''));
        $startDateInput = $request->query('start_date');
        $endDateInput = $request->query('end_date');

        $now = Carbon::now();
        switch ($period) {
            case 'yesterday':
                $startDate = $now->copy()->subDay()->startOfDay();
                $endDate = $now->copy()->subDay()->endOfDay();
                break;
            case 'this_week':
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                break;
            case 'this_month':
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                break;
            case 'custom':
                $startDate = $startDateInput ? Carbon::parse($startDateInput)->startOfDay() : $now->copy()->startOfDay();
                $endDate = $endDateInput ? Carbon::parse($endDateInput)->endOfDay() : $now->copy()->endOfDay();
                break;
            case 'today':
            default:
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
                break;
        }

        $query = DeliveryOrder::query()->whereBetween('created_at', [$startDate, $endDate])->latest();

        if ($channelFilter !== 'all') {
            $query->where('channel', $channelFilter);
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if (! empty($searchQuery)) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('order_number', 'like', "%{$searchQuery}%")
                    ->orWhere('customer_name', 'like', "%{$searchQuery}%")
                    ->orWhere('customer_phone', 'like', "%{$searchQuery}%")
                    ->orWhere('delivery_address', 'like', "%{$searchQuery}%");
            });
        }

        $orders = $query->paginate(25)->withQueryString();

        $statsQuery = DeliveryOrder::query()->whereBetween('created_at', [$startDate, $endDate]);
        $stats = [
            'total_count' => (clone $statsQuery)->count(),
            'total_revenue' => (clone $statsQuery)->where('status', 'delivered')->sum('total'),
            'delivered_count' => (clone $statsQuery)->where('status', 'delivered')->count(),
            'cancelled_count' => (clone $statsQuery)->where('status', 'cancelled')->count(),
        ];

        return view('delivery.history', compact(
            'orders',
            'stats',
            'period',
            'channelFilter',
            'statusFilter',
            'searchQuery',
            'startDate',
            'endDate'
        ));
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
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query
                        ->where('branch_id', $request->user()->branch_id)
                        ->where('is_active', true)),
            ],
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string|max:255',
        ]);

        $products = Product::whereIn(
            'id',
            collect($validated['items'])->pluck('product_id')->unique()
        )->where('is_active', true)->get()->keyBy('id');

        $items = collect($validated['items'])->map(function (array $item) use ($products): array {
            $product = $products->get($item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'Siparişte geçersiz veya pasif ürün bulunuyor.',
                ]);
            }

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->effective_price,
                'quantity' => $item['quantity'],
                'note' => $item['note'] ?? null,
            ];
        })->values()->all();

        $orderNumber = 'TEL-'.strtoupper(substr(uniqid(), -6));
        $subtotal = collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']);

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
            'items' => $items,
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

        if ($validated['status'] === 'preparing' && ! $order->accepted_at) {
            $order->accepted_at = now();
        }

        if ($validated['status'] === 'on_the_way') {
            $order->dispatched_at = now();
            if (! empty($validated['courier_name'])) {
                $order->courier_name = $validated['courier_name'];
            }
            if (! empty($validated['courier_phone'])) {
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
            'integrations.*.channel' => ['required', Rule::in(['trendyol', 'yemeksepeti', 'getir', 'migros'])],
            'integrations.*.store_name' => 'nullable|string|max:255',
            'integrations.*.store_id' => 'nullable|string|max:255',
            'integrations.*.api_key' => 'nullable|string|max:2048',
            'integrations.*.api_secret' => 'nullable|string|max:2048',
            'integrations.*.is_active' => 'required|boolean',
            'integrations.*.auto_accept' => 'required|boolean',
        ]);

        foreach ($validated['integrations'] as $data) {
            $integration = DeliveryIntegration::firstOrNew(['channel' => $data['channel']]);
            $integration->fill([
                'store_name' => $data['store_name'] ?? null,
                'store_id' => $data['store_id'] ?? null,
                'is_active' => $data['is_active'],
                'auto_accept' => $data['auto_accept'],
            ]);

            if (isset($data['api_key']) && trim($data['api_key']) !== '') {
                $integration->api_key = trim($data['api_key']);
            }
            if (isset($data['api_secret']) && trim($data['api_secret']) !== '') {
                $integration->api_secret = trim($data['api_secret']);
            }

            $integration->save();
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
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['all', 'trendyol', 'yemeksepeti', 'getir', 'migros'])],
            'is_active' => 'required|boolean',
        ]);
        $channel = $validated['channel'];
        $isActive = (bool) $validated['is_active'];

        if ($channel === 'all') {
            DeliveryIntegration::query()->update(['is_active' => $isActive]);

            return response()->json([
                'success' => true,
                'message' => 'Tüm platform kanalları '.($isActive ? 'açıldı' : 'kapatıldı'),
            ]);
        }

        $integration = DeliveryIntegration::where('channel', $channel)->first();
        if ($integration) {
            $integration->update(['is_active' => $isActive]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($channel).' kanalı '.($isActive ? 'açıldı' : 'kapatıldı'),
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Kanal bulunamadı'], 404);
    }

    /**
     * Toggle global auto accept mode for all integration channels.
     */
    public function toggleAutoAccept(Request $request)
    {
        $validated = $request->validate([
            'is_auto' => 'required|boolean',
        ]);
        $isAuto = (bool) $validated['is_auto'];

        $defaultChannels = ['trendyol', 'yemeksepeti', 'getir', 'migros'];
        foreach ($defaultChannels as $ch) {
            DeliveryIntegration::updateOrCreate(
                ['channel' => $ch],
                [
                    'store_name' => ucfirst($ch).' Restoran',
                    'auto_accept' => $isAuto,
                    'is_active' => true,
                ]
            );
        }

        try {
            Setting::updateOrCreate(
                ['key' => 'delivery_global_auto_accept'],
                ['value' => $isAuto ? '1' : '0']
            );
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'message' => $isAuto ? 'Otomatik onay modu tüm kanallar için aktif edildi.' : 'Otomatik onay kapatıldı.',
            'is_auto' => $isAuto,
        ]);
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
        $subtotal = collect($selectedItems)->sum(fn ($i) => $i['price'] * $i['quantity']);

        $channelPrefixes = [
            'trendyol' => 'TRN',
            'yemeksepeti' => 'YS',
            'getir' => 'GTR',
            'migros' => 'MGR',
        ];

        $prefix = $channelPrefixes[$channel] ?? 'ORD';
        $orderNumber = $prefix.'-'.rand(100000, 999999);
        $platformOrderId = '#'.strtoupper(substr(md5(microtime()), 0, 8));

        // Check if channel or global auto-accept is enabled
        $integration = DeliveryIntegration::where('channel', $channel)->first();
        $autoAccept = $integration ? (bool) $integration->auto_accept : false;
        if (! $autoAccept) {
            $autoAccept = DeliveryIntegration::where('auto_accept', true)->exists();
        }

        $order = DeliveryOrder::create([
            'channel' => $channel,
            'platform_order_id' => $platformOrderId,
            'order_number' => $orderNumber,
            'customer_name' => $sampleNames[array_rand($sampleNames)],
            'customer_phone' => '05'.rand(30, 55).' '.rand(100, 999).' '.rand(10, 99).' '.rand(10, 99),
            'delivery_address' => $sampleAddresses[array_rand($sampleAddresses)],
            'address_note' => 'Zile basmayın, kapıya bırakabilirsiniz.',
            'payment_method' => 'online',
            'payment_status' => 'paid',
            'status' => $autoAccept ? 'preparing' : 'new',
            'courier_type' => 'platform',
            'courier_name' => $channel === 'trendyol' ? 'Trendyol Express Kurye' : ucfirst($channel).' Kuryesi',
            'subtotal' => $subtotal,
            'delivery_fee' => 15.00,
            'total' => $subtotal + 15.00,
            'items' => $selectedItems,
            'received_at' => now(),
            'accepted_at' => $autoAccept ? now() : null,
        ]);

        // SQLite Çift Yazma Koruması
        try {
            if (Schema::connection('sqlite')->hasTable('delivery_orders')) {
                DB::connection('sqlite')->table('delivery_orders')->updateOrInsert(
                    ['platform_order_id' => $order->platform_order_id],
                    [
                        'branch_id' => $order->branch_id,
                        'channel' => $order->channel,
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
                        'discount_total' => $order->discount_total ?? 0,
                        'total' => $order->total,
                        'items' => json_encode($order->items),
                        'received_at' => $order->received_at ?? now(),
                        'created_at' => $order->created_at ?? now(),
                        'updated_at' => $order->updated_at ?? now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::channel('sync')->warning('DeliveryController SQLite sync: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => strtoupper($channel).' üzerinden yeni sipariş simüle edildi!',
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
