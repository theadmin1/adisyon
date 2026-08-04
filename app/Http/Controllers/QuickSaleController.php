<?php

namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Models\Category;
use App\Models\Check;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\AutoSyncService;
use App\Services\Checks\CheckService;
use App\Services\KitchenDispatchService;
use App\Support\PaymentMethods;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        $paymentMethods = PaymentMethods::active((int) $request->user()->branch_id);

        return view('quicksale.index', compact('categories', 'products', 'halls', 'tables', 'paymentMethods'));
    }

    public function store(Request $request, CheckService $checkService, AuditLogger $auditLogger, KitchenDispatchService $dispatchService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'payment_method' => ['required', 'string', Rule::in(PaymentMethods::activeIds((int) $request->user()->branch_id))],
            'discount_amount' => 'nullable|numeric|min:0',
            'send_to_kitchen' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $branchId = (int) $user->branch_id;
        $sendToKitchen = $request->has('send_to_kitchen') ? (bool) $request->send_to_kitchen : true;

        $check = DB::transaction(function () use ($validated, $user, $branchId, $checkService) {
            $check = Check::create([
                'branch_id' => $branchId,
                'dining_table_id' => null,
                'waiter_id' => $user?->id,
                'check_number' => 'QCK-'.Str::upper(Str::random(8)),
                'sync_uuid' => (string) Str::uuid(),
                'is_synced' => config('database.default') === 'mysql',
                'guest_count' => 1,
                'status' => CheckStatus::Open,
                'discount_total' => $validated['discount_amount'] ?? 0,
                'kitchen_sent_at' => null,
                'opened_at' => now(),
            ]);

            // Ürün kalemlerini adisyona ekle
            $check = $checkService->addItems($check, $validated['items']);

            // Ödeme kaydını oluştur
            $paymentMethod = $validated['payment_method'];
            $amount = $check->total;

            if ($amount > 0) {
                $check->payments()->create([
                    'branch_id' => $branchId,
                    'payment_method' => $paymentMethod,
                    'amount' => $amount,
                    'sync_uuid' => (string) Str::uuid(),
                    'is_synced' => config('database.default') === 'mysql',
                ]);
            }

            // Adisyonu kapat
            $checkService->closeCheck($check, $user);

            return $check;
        });

        if ($sendToKitchen) {
            $dispatchService->send($check);
        }

        $auditLogger->record(
            action: 'quick_sale.completed',
            subject: $check,
            newValues: [
                'payment_method' => $validated['payment_method'],
                'discount_total' => $check->discount_total,
                'total' => $check->total,
                'item_count' => count($validated['items']),
                'sent_to_kitchen' => $sendToKitchen,
            ],
            description: 'Hızlı satış tamamlandı.',
            category: 'sales',
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Hızlı satış başarıyla tamamlandı.',
                'check_number' => $check->check_number,
                'total' => number_format($check->total, 2),
            ]);
        }

        return redirect()->route('quicksale.index')
            ->with('status', "Satış tamamlandı (#{$check->check_number} - ₺".number_format($check->total, 2).')');
    }

    /**
     * Hızlı Satış Sepetini Masaya Aktarma
     */
    public function transferToTable(Request $request, CheckService $checkService, AuditLogger $auditLogger, KitchenDispatchService $dispatchService): JsonResponse
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

        $check = DB::transaction(function () use ($table, $validated, $user, $checkService) {
            $activeCheck = $table->activeCheck;
            if (! $activeCheck) {
                $activeCheck = $checkService->openCheck($table, $user);
            }

            $activeCheck = $checkService->addItems($activeCheck, $validated['items']);

            return $activeCheck;
        });

        if ($sendToKitchen) {
            $dispatchService->send($check);
        }

        $auditLogger->record(
            action: 'quick_sale.transferred_to_table',
            subject: $check,
            newValues: [
                'dining_table_id' => $table->id,
                'table_name' => $table->name,
                'item_count' => count($validated['items']),
                'sent_to_kitchen' => $sendToKitchen,
            ],
            description: 'Hızlı satış sepeti masaya aktarıldı.',
            category: 'sales',
        );

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
            ->with(['items' => function ($q) {
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
    public function holdSale(Request $request, CheckService $checkService, AuditLogger $auditLogger): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        $user = $request->user();
        $branchId = (int) $user->branch_id;

        $check = DB::transaction(function () use ($validated, $user, $branchId, $checkService) {
            $check = Check::create([
                'branch_id' => $branchId,
                'dining_table_id' => null,
                'waiter_id' => $user?->id,
                'check_number' => 'QCK-'.Str::upper(Str::random(8)),
                'sync_uuid' => (string) Str::uuid(),
                'is_synced' => config('database.default') === 'mysql',
                'guest_count' => 1,
                'status' => CheckStatus::Open,
                'discount_total' => $validated['discount_amount'] ?? 0,
                'opened_at' => now(),
            ]);

            $check = $checkService->addItems($check, $validated['items']);

            return $check;
        });

        AutoSyncService::syncIfLocal();

        $auditLogger->record(
            action: 'quick_sale.held',
            subject: $check,
            newValues: [
                'discount_total' => $check->discount_total,
                'total' => $check->total,
                'item_count' => count($validated['items']),
            ],
            description: 'Hızlı satış beklemeye alındı.',
            category: 'sales',
        );

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
        $check->load(['items' => function ($q) {
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
    public function updateSale(Request $request, Check $check, CheckService $checkService, AuditLogger $auditLogger, KitchenDispatchService $dispatchService): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'payment_method' => ['nullable', 'string', Rule::in(PaymentMethods::activeIds((int) $request->user()->branch_id))],
            'discount_amount' => 'nullable|numeric|min:0',
            'complete_sale' => 'nullable|boolean',
            'send_to_kitchen' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $completeSale = $request->boolean('complete_sale', true);
        $sendToKitchen = $request->has('send_to_kitchen') ? $request->boolean('send_to_kitchen') : true;
        $before = [
            'status' => $check->status,
            'discount_total' => $check->discount_total,
            'total' => $check->total,
            'items' => $check->items()
                ->where('is_cancelled', false)
                ->get(['product_id', 'product_name', 'quantity'])
                ->toArray(),
        ];

        $check = DB::transaction(function () use ($check, $validated, $user, $checkService, $completeSale) {
            $isSynced = config('database.default') === 'mysql';

            // 1. Mevcut aktif (iptal edilmemiş) kalemleri al
            $oldItems = $check->items()->where('is_cancelled', false)->get();
            $newItemsMap = collect($validated['items'])->keyBy('product_id');

            // 2. Silinen veya Miktarı Düşen Ürünlerin Stoklarını Geri Yükle
            foreach ($oldItems as $oldItem) {
                $pid = $oldItem->product_id;
                if (! $newItemsMap->has($pid)) {
                    // Ürün yeni sepette yok -> İptal et ve stoğu geri iade et
                    $oldItem->update(['is_cancelled' => true, 'is_synced' => $isSynced]);
                    if ($oldItem->product_id) {
                        $product = Product::where('branch_id', $check->branch_id)
                            ->whereKey($oldItem->product_id)
                            ->lockForUpdate()
                            ->first();
                        if ($product && $product->track_stock) {
                            $product->increment('stock_quantity', $oldItem->quantity);
                        }
                        StockMovement::create([
                            'sync_uuid' => (string) Str::uuid(),
                            'is_synced' => $isSynced,
                            'product_id' => $oldItem->product_id,
                            'check_id' => $check->id,
                            'type' => 'return_approved',
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
                            $product = Product::where('branch_id', $check->branch_id)
                                ->whereKey($oldItem->product_id)
                                ->lockForUpdate()
                                ->first();
                            if ($product && $product->track_stock) {
                                if ($diff > 0) {
                                    if ((float) $product->stock_quantity < $diff) {
                                        throw ValidationException::withMessages([
                                            'items' => "{$product->name} için yeterli stok yok.",
                                        ]);
                                    }
                                    $product->decrement('stock_quantity', $diff);
                                } else {
                                    $product->increment('stock_quantity', abs($diff));
                                }
                            }
                            StockMovement::create([
                                'sync_uuid' => (string) Str::uuid(),
                                'is_synced' => $isSynced,
                                'product_id' => $oldItem->product_id,
                                'check_id' => $check->id,
                                'type' => $diff > 0 ? 'sale_deduction' : 'return_approved',
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
                if (! in_array($item['product_id'], $oldProductIds)) {
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
                if ($check->total > 0) {
                    $payment = $check->payments()->lockForUpdate()->first();
                    $paymentValues = [
                        'branch_id' => $check->branch_id,
                        'payment_method' => $paymentMethod,
                        'amount' => $check->total,
                        'is_synced' => $isSynced,
                    ];

                    if ($payment) {
                        $payment->update($paymentValues);
                    } else {
                        $check->payments()->create($paymentValues + [
                            'sync_uuid' => (string) Str::uuid(),
                        ]);
                    }
                }
                $checkService->closeCheck($check, $user);
            }

            return $check;
        });

        if ($sendToKitchen) {
            $dispatchService->send($check);
        }

        AutoSyncService::syncIfLocal();

        $freshCheck = $check->fresh();
        $auditLogger->record(
            action: 'quick_sale.updated',
            subject: $freshCheck,
            oldValues: $before,
            newValues: [
                'status' => $freshCheck->status,
                'discount_total' => $freshCheck->discount_total,
                'total' => $freshCheck->total,
                'items' => $freshCheck->items()
                    ->where('is_cancelled', false)
                    ->get(['product_id', 'product_name', 'quantity'])
                    ->toArray(),
            ],
            description: 'Hızlı satış düzenlendi.',
            category: 'sales',
        );

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
    public function cancelSale(Request $request, Check $check, CheckService $checkService, AuditLogger $auditLogger): JsonResponse
    {
        $before = [
            'status' => $check->status,
            'subtotal' => $check->subtotal,
            'discount_total' => $check->discount_total,
            'total' => $check->total,
            'active_item_count' => $check->items()->where('is_cancelled', false)->count(),
        ];

        DB::transaction(function () use ($check) {
            $isSynced = config('database.default') === 'mysql';
            $activeItems = $check->items()->where('is_cancelled', false)->get();

            foreach ($activeItems as $item) {
                $item->update(['is_cancelled' => true, 'is_synced' => $isSynced]);
                if ($item->product_id) {
                    $product = Product::where('branch_id', $check->branch_id)
                        ->whereKey($item->product_id)
                        ->lockForUpdate()
                        ->first();
                    if ($product && $product->track_stock) {
                        $product->increment('stock_quantity', $item->quantity);
                    }
                    StockMovement::create([
                        'sync_uuid' => (string) Str::uuid(),
                        'is_synced' => $isSynced,
                        'product_id' => $item->product_id,
                        'check_id' => $check->id,
                        'type' => 'return_approved',
                        'quantity' => $item->quantity,
                        'status' => 'completed',
                        'notes' => "Hızlı Satış İptal / İade (#{$check->check_number})",
                    ]);
                }
            }

            $check->payments()->update([
                'amount' => 0,
                'is_synced' => $isSynced,
            ]);
            $check->update([
                'status' => CheckStatus::Closed,
                'closed_at' => now(),
                'subtotal' => 0,
                'discount_total' => 0,
                'total' => 0,
                'is_synced' => $isSynced,
            ]);
        });

        AutoSyncService::syncIfLocal();

        $auditLogger->record(
            action: 'quick_sale.cancelled',
            subject: $check,
            oldValues: $before,
            newValues: $check->fresh()->only(['status', 'subtotal', 'discount_total', 'total']),
            description: 'Hızlı satış iptal edildi ve stoklar iade edildi.',
            category: 'sales',
        );

        return response()->json([
            'success' => true,
            'message' => "Satış (#{$check->check_number}) başarıyla iptal edildi ve stoklar iade edildi.",
        ]);
    }

    /**
     * Kapanmış Hızlı Satışı Tekrar Açma / Geri Getirme
     */
    public function reopenSale(Request $request, Check $check, CheckService $checkService, AuditLogger $auditLogger): JsonResponse
    {
        $oldStatus = $check->status;
        $oldClosedAt = $check->closed_at;
        $checkService->reopenCheck($check, $request->user());

        $auditLogger->record(
            action: 'quick_sale.reopened',
            subject: $check,
            oldValues: ['status' => $oldStatus, 'closed_at' => $oldClosedAt],
            newValues: ['status' => $check->fresh()->status, 'closed_at' => $check->fresh()->closed_at],
            description: 'Hızlı satış yeniden açıldı.',
            category: 'sales',
        );

        return response()->json([
            'success' => true,
            'message' => "Satış (#{$check->check_number}) başarıyla geri getirildi ve tekrar açıldı.",
            'check_number' => $check->check_number,
        ]);
    }
}
