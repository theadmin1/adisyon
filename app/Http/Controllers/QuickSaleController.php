<?php

namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Check;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Product;
use App\Services\Checks\CheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuickSaleController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::where('is_active', true)
            ->withCount(['products' => function ($q) {
                $q->where('is_active', true);
            }])
            ->orderBy('sort_order')
            ->get();

        $products = Product::where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->get();

        $halls = Hall::where('is_active', true)
            ->with(['tables' => function ($q) {
                $q->where('is_active', true)->with('activeCheck');
            }])
            ->orderBy('sort_order')
            ->get();

        $tables = DiningTable::where('is_active', true)->with(['hall', 'activeCheck'])->get();

        return view('quicksale.index', compact('categories', 'products', 'halls', 'tables'));
    }

    public function store(Request $request, CheckService $checkService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:nakit,kredi_karti,yemek_karti',
            'discount_amount' => 'nullable|numeric|min:0',
            'send_to_kitchen' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $branchId = Branch::first()?->id ?? 1;
        $sendToKitchen = $request->has('send_to_kitchen') ? (bool) $request->send_to_kitchen : true;

        $check = DB::transaction(function () use ($validated, $user, $branchId, $checkService, $sendToKitchen) {
            $check = Check::create([
                'branch_id' => $branchId,
                'dining_table_id' => null,
                'waiter_id' => $user?->id,
                'check_number' => 'QCK-' . Str::upper(Str::random(8)),
                'guest_count' => 1,
                'status' => CheckStatus::Open,
                'discount_total' => $validated['discount_amount'] ?? 0,
                'kitchen_sent_at' => $sendToKitchen ? now() : null,
                'opened_at' => now(),
            ]);

            // Ürün kalemlerini adisyona ekle
            $check = $checkService->addItems($check, $validated['items']);

            if ($sendToKitchen) {
                foreach ($check->items as $item) {
                    $item->update([
                        'kitchen_status' => 'received',
                    ]);
                }
            }

            // Ödeme kaydını oluştur
            $paymentMethod = $validated['payment_method'];
            $amount = $check->total;

            if ($amount > 0) {
                $check->payments()->create([
                    'payment_method' => $paymentMethod,
                    'amount' => $amount,
                ]);
            }

            // Adisyonu kapat
            $checkService->closeCheck($check, $user);

            return $check;
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Hızlı satış başarıyla tamamlandı.',
                'check_number' => $check->check_number,
                'total' => number_format($check->total, 2),
            ]);
        }

        return redirect()->route('quicksale.index')
            ->with('status', "Satış tamamlandı (#{$check->check_number} - ₺" . number_format($check->total, 2) . ")");
    }

    /**
     * Hızlı Satış Sepetini Masaya Aktarma
     */
    public function transferToTable(Request $request, CheckService $checkService): JsonResponse
    {
        $validated = $request->validate([
            'dining_table_id' => 'required|exists:dining_tables,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'send_to_kitchen' => 'nullable|boolean',
        ]);

        $table = DiningTable::findOrFail($validated['dining_table_id']);
        $user = $request->user();
        $sendToKitchen = $request->has('send_to_kitchen') ? (bool) $request->send_to_kitchen : true;

        $check = DB::transaction(function () use ($table, $validated, $user, $checkService, $sendToKitchen) {
            $activeCheck = $table->activeCheck;
            if (!$activeCheck) {
                $activeCheck = $checkService->openCheck($table, $user);
            }

            $activeCheck = $checkService->addItems($activeCheck, $validated['items']);

            if ($sendToKitchen) {
                foreach ($activeCheck->items as $item) {
                    if (!$item->kitchen_status) {
                        $item->update(['kitchen_status' => 'received']);
                    }
                }
                $activeCheck->update(['kitchen_sent_at' => now()]);
            }

            return $activeCheck;
        });

        return response()->json([
            'success' => true,
            'message' => "Sepet {$table->name} masasına başarıyla aktarıldı.",
            'redirect_url' => route('tables.show', $table),
        ]);
    }

    /**
     * Bekleyen ve Son Hızlı Satışları Listeleme
     */
    public function recentSales(Request $request): JsonResponse
    {
        $status = $request->query('status', 'all');

        $query = Check::whereNull('dining_table_id')
            ->with(['items' => function($q) {
                $q->where('is_cancelled', false)->with('product');
            }, 'payments'])
            ->orderBy('id', 'desc');

        if ($status === 'open') {
            $query->where('status', CheckStatus::Open);
        } elseif ($status === 'closed') {
            $query->where('status', CheckStatus::Closed);
        }

        $sales = $query->limit(40)->get()->map(function ($c) {
            $paymentMethod = $c->payments->first()?->payment_method ?? 'nakit';
            return [
                'id' => $c->id,
                'sync_uuid' => $c->sync_uuid,
                'check_number' => $c->check_number,
                'status' => is_object($c->status) ? $c->status->value : $c->status,
                'subtotal' => (float) $c->subtotal,
                'discount_total' => (float) $c->discount_total,
                'total' => (float) $c->total,
                'payment_method' => $paymentMethod,
                'opened_at' => $c->opened_at ? $c->opened_at->format('H:i - d.m.Y') : ($c->created_at ? $c->created_at->format('H:i - d.m.Y') : ''),
                'closed_at' => $c->closed_at ? $c->closed_at->format('H:i - d.m.Y') : '',
                'item_count' => $c->items->count(),
                'items' => $c->items->map(function ($i) {
                    return [
                        'id' => $i->id,
                        'product_id' => $i->product_id,
                        'product_name' => $i->product_name,
                        'unit_price' => (float) $i->unit_price,
                        'quantity' => (float) $i->quantity,
                        'total_price' => (float) $i->total_price,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'sales' => $sales,
        ]);
    }

    /**
     * Hızlı Satışı Beklemeye Alma (Park Etme)
     */
    public function holdSale(Request $request, CheckService $checkService): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        $user = $request->user();
        $branchId = Branch::first()?->id ?? 1;

        $check = DB::transaction(function () use ($validated, $user, $branchId, $checkService) {
            $check = Check::create([
                'branch_id' => $branchId,
                'dining_table_id' => null,
                'waiter_id' => $user?->id,
                'check_number' => 'QCK-' . Str::upper(Str::random(8)),
                'guest_count' => 1,
                'status' => CheckStatus::Open,
                'discount_total' => $validated['discount_amount'] ?? 0,
                'opened_at' => now(),
            ]);

            $check = $checkService->addItems($check, $validated['items']);
            return $check;
        });

        \App\Services\AutoSyncService::syncIfLocal();

        return response()->json([
            'success' => true,
            'message' => "Satış beklemeye alındı (#{$check->check_number}).",
            'check_number' => $check->check_number,
            'check_id' => $check->id,
        ]);
    }

    /**
     * Belirli Bir Hızlı Satışın Detaylarını Getirme
     */
    public function showSale(Check $check): JsonResponse
    {
        $check->load(['items' => function($q) {
            $q->where('is_cancelled', false)->with('product');
        }, 'payments']);

        return response()->json([
            'success' => true,
            'check' => [
                'id' => $check->id,
                'sync_uuid' => $check->sync_uuid,
                'check_number' => $check->check_number,
                'status' => is_object($check->status) ? $check->status->value : $check->status,
                'subtotal' => (float) $check->subtotal,
                'discount_total' => (float) $check->discount_total,
                'total' => (float) $check->total,
                'payment_method' => $check->payments->first()?->payment_method ?? 'nakit',
                'items' => $check->items->map(function ($i) {
                    return [
                        'id' => $i->id,
                        'product_id' => $i->product_id,
                        'product_name' => $i->product_name,
                        'unit_price' => (float) $i->unit_price,
                        'quantity' => (float) $i->quantity,
                        'total_price' => (float) $i->total_price,
                        'product' => $i->product ? [
                            'id' => $i->product->id,
                            'name' => $i->product->name,
                            'image' => $i->product->image_url ?? '/images/product-placeholder.png',
                        ] : null,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Tamamlanmış Veya Bekleyen Hızlı Satışı Düzenleme / Değiştirme / Ödeme Alma
     */
    public function updateSale(Request $request, Check $check, CheckService $checkService): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|in:nakit,kredi_karti,yemek_karti',
            'discount_amount' => 'nullable|numeric|min:0',
            'complete_sale' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $completeSale = $request->boolean('complete_sale', true);

        $check = DB::transaction(function () use ($check, $validated, $user, $checkService, $completeSale) {
            $isSynced = config('database.default') === 'mysql';

            // 1. Mevcut aktif (iptal edilmemiş) kalemleri al
            $oldItems = $check->items()->where('is_cancelled', false)->get();
            $newItemsMap = collect($validated['items'])->keyBy('product_id');

            // 2. Silinen veya Miktarı Düşen Ürünlerin Stoklarını Geri Yükle
            foreach ($oldItems as $oldItem) {
                $pid = $oldItem->product_id;
                if (!$newItemsMap->has($pid)) {
                    // Ürün yeni sepette yok -> İptal et ve stoğu geri iade et
                    $oldItem->update(['is_cancelled' => true, 'is_synced' => $isSynced]);
                    if ($oldItem->product_id) {
                        $product = Product::find($oldItem->product_id);
                        if ($product && $product->track_stock) {
                            $product->increment('stock_quantity', $oldItem->quantity);
                        }
                        \App\Models\StockMovement::create([
                            'sync_uuid' => (string) Str::uuid(),
                            'is_synced' => $isSynced,
                            'product_id' => $oldItem->product_id,
                            'check_id' => $check->id,
                            'type' => 'cancellation_pending',
                            'quantity' => $oldItem->quantity,
                            'status' => 'completed',
                            'notes' => "Hızlı Satış Düzenleme: Kalem Çıkarıldı (#{$check->check_number})",
                        ]);
                    }
                } else {
                    // Ürün var -> miktar farkına bak
                    $newItemData = $newItemsMap->get($pid);
                    $newQty = (float) $newItemData['quantity'];
                    $oldQty = (float) $oldItem->quantity;
                    $diff = $newQty - $oldQty;

                    if ($diff != 0) {
                        $oldItem->update([
                            'quantity' => $newQty,
                            'total_price' => $oldItem->unit_price * $newQty,
                            'is_synced' => $isSynced,
                        ]);

                        if ($oldItem->product_id) {
                            $product = Product::find($oldItem->product_id);
                            if ($product && $product->track_stock) {
                                if ($diff > 0) {
                                    $product->decrement('stock_quantity', $diff);
                                } else {
                                    $product->increment('stock_quantity', abs($diff));
                                }
                            }
                            \App\Models\StockMovement::create([
                                'sync_uuid' => (string) Str::uuid(),
                                'is_synced' => $isSynced,
                                'product_id' => $oldItem->product_id,
                                'check_id' => $check->id,
                                'type' => $diff > 0 ? 'sale_deduction' : 'cancellation_pending',
                                'quantity' => abs($diff),
                                'status' => 'completed',
                                'notes' => "Hızlı Satış Miktar Değişimi (#{$check->check_number})",
                            ]);
                        }
                    }
                }
            }

            // 3. Tamamen Yeni Eklenen Ürünleri Ekle
            $oldProductIds = $oldItems->pluck('product_id')->filter()->toArray();
            foreach ($validated['items'] as $item) {
                if (!in_array($item['product_id'], $oldProductIds)) {
                    $checkService->addItems($check, [$item]);
                }
            }

            // 4. İskonto ve Toplamları Yeniden Hesapla
            $check->update([
                'discount_total' => $validated['discount_amount'] ?? $check->discount_total,
                'is_synced' => $isSynced,
            ]);
            $checkService->recalculateTotals($check);

            // 5. Tamamlama / Ödeme İşlemi
            if ($completeSale) {
                $paymentMethod = $validated['payment_method'] ?? 'nakit';
                $check->payments()->delete(); // Eski ödemeyi yenile
                if ($check->total > 0) {
                    $check->payments()->create([
                        'payment_method' => $paymentMethod,
                        'amount' => $check->total,
                        'is_synced' => $isSynced,
                    ]);
                }
                $checkService->closeCheck($check, $user);
            }

            return $check;
        });

        \App\Services\AutoSyncService::syncIfLocal();

        return response()->json([
            'success' => true,
            'message' => "Satış başarıyla güncellendi (#{$check->check_number}).",
            'check_number' => $check->check_number,
            'total' => number_format($check->total, 2),
        ]);
    }

    /**
     * Hızlı Satışı İptal Etme / İade Etme (Stokları Geri Yükler)
     */
    public function cancelSale(Request $request, Check $check, CheckService $checkService): JsonResponse
    {
        DB::transaction(function () use ($check, $checkService) {
            $isSynced = config('database.default') === 'mysql';
            $activeItems = $check->items()->where('is_cancelled', false)->get();

            foreach ($activeItems as $item) {
                $item->update(['is_cancelled' => true, 'is_synced' => $isSynced]);
                if ($item->product_id) {
                    $product = Product::find($item->product_id);
                    if ($product && $product->track_stock) {
                        $product->increment('stock_quantity', $item->quantity);
                    }
                    \App\Models\StockMovement::create([
                        'sync_uuid' => (string) Str::uuid(),
                        'is_synced' => $isSynced,
                        'product_id' => $item->product_id,
                        'check_id' => $check->id,
                        'type' => 'cancellation_pending',
                        'quantity' => $item->quantity,
                        'status' => 'completed',
                        'notes' => "Hızlı Satış İptal / İade (#{$check->check_number})",
                    ]);
                }
            }

            $check->payments()->delete();
            $check->update([
                'status' => CheckStatus::Closed,
                'closed_at' => now(),
                'subtotal' => 0,
                'discount_total' => 0,
                'total' => 0,
                'is_synced' => $isSynced,
            ]);
        });

        \App\Services\AutoSyncService::syncIfLocal();

        return response()->json([
            'success' => true,
            'message' => "Satış (#{$check->check_number}) başarıyla iptal edildi ve stoklar iade edildi.",
        ]);
    }

    /**
     * Kapanmış Hızlı Satışı Tekrar Açma / Geri Getirme
     */
    public function reopenSale(Request $request, Check $check, CheckService $checkService): JsonResponse
    {
        $checkService->reopenCheck($check, $request->user());

        return response()->json([
            'success' => true,
            'message' => "Satış (#{$check->check_number}) başarıyla geri getirildi ve tekrar açıldı.",
            'check_number' => $check->check_number,
        ]);
    }
}
