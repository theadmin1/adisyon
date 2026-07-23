<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockController extends Controller
{
    /**
     * Stok Yönetimi & Takip Portalı
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $tab = $request->query('tab', 'list'); // list, pending_returns, movements

        $productsQuery = Product::with('category')
            ->orderBy('name', 'asc');

        if ($search) {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $productsQuery->paginate(20)->withQueryString();

        // Her ürün için toplam düşen (satılan) stok ve toplam iptal miktarlarını hesapla
        $stockStatsMap = StockMovement::select('product_id', 'type', DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('product_id', 'type')
            ->get()
            ->groupBy('product_id');

        // Onay bekleyen iptal iadeleri
        $pendingReturns = StockMovement::where('type', 'cancellation_pending')
            ->where('status', 'pending_approval')
            ->with(['product.category', 'check.diningTable', 'checkItem'])
            ->latest()
            ->get();

        // Stok Hareket Günlüğü
        $movements = StockMovement::with(['product', 'check.diningTable', 'approvedByUser'])
            ->latest()
            ->paginate(30, ['*'], 'movements_page')
            ->withQueryString();

        // Özet İstatistikler
        $stats = [
            'total_products' => Product::count(),
            'total_stock' => Product::sum('stock_quantity'),
            'critical_stock_count' => Product::whereRaw('stock_quantity <= min_stock_level')->count(),
            'pending_returns_count' => $pendingReturns->count(),
            'total_deductions' => StockMovement::where('type', 'sale_deduction')->sum('quantity'),
        ];

        return view('stocks.index', compact('products', 'pendingReturns', 'movements', 'stats', 'stockStatsMap', 'tab', 'search'));
    }

    /**
     * Stok Miktarını ve Stok Kodunu (SKU) Manuel Güncelleme
     */
    public function updateStock(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|max:50',
            'stock_quantity' => 'required|numeric|min:0',
            'min_stock_level' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'track_stock' => 'nullable|boolean',
        ]);

        $oldQuantity = $product->stock_quantity;
        $newQuantity = (float) $validated['stock_quantity'];
        $diff = $newQuantity - $oldQuantity;

        $product->update([
            'sku' => $validated['sku'],
            'stock_quantity' => $newQuantity,
            'min_stock_level' => $validated['min_stock_level'],
            'unit' => $validated['unit'],
            'track_stock' => $request->has('track_stock') ? (bool) $request->track_stock : true,
        ]);

        if ($diff != 0) {
            StockMovement::create([
                'product_id' => $product->id,
                'type' => $diff > 0 ? 'manual_addition' : 'manual_subtraction',
                'quantity' => abs($diff),
                'status' => 'completed',
                'approved_by_user_id' => auth()->id(),
                'notes' => 'Manuel stok miktarı güncellemesi (' . ($diff > 0 ? "+{$diff}" : "{$diff}") . ' ' . $product->unit . ')',
            ]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "{$product->name} stok bilgileri güncellendi.",
                'stock_quantity' => $product->stock_quantity,
            ]);
        }

        return redirect()->back()->with('status', "{$product->name} stok bilgileri güncellendi.");
    }

    /**
     * İptal Edilen Ürünü Stoka İade Etmeyi Onaylama (+Stok)
     */
    public function approveReturn(Request $request, StockMovement $movement): JsonResponse|RedirectResponse
    {
        if ($movement->status !== 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'Bu işlem zaten tamamlanmış.'], 422);
        }

        DB::transaction(function () use ($movement) {
            // Stok miktarını geri ekle
            $movement->product->increment('stock_quantity', $movement->quantity);

            $movement->update([
                'type' => 'return_approved',
                'status' => 'approved',
                'approved_by_user_id' => auth()->id(),
                'approved_at' => now(),
                'notes' => 'İptal edilen ürün stoka iade edildi (+' . $movement->quantity . ' ' . $movement->product->unit . ')',
            ]);
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'İptal edilen ürün stoğa başarıyla iade edildi!',
            ]);
        }

        return redirect()->back()->with('status', 'İptal edilen ürün stoğa başarıyla iade edildi!');
    }

    /**
     * İptal Edilen Ürünü Fire / Zayi Olarak İşaretleme (Stoka Eklemez)
     */
    public function rejectReturn(Request $request, StockMovement $movement): JsonResponse|RedirectResponse
    {
        if ($movement->status !== 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'Bu işlem zaten tamamlanmış.'], 422);
        }

        $movement->update([
            'status' => 'rejected',
            'approved_by_user_id' => auth()->id(),
            'approved_at' => now(),
            'notes' => 'Fire / Zayi olarak kaydedildi (Stoka iade edilmedi).',
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Ürün fire/zayi olarak kaydedildi (stoka aktarılmadı).',
            ]);
        }

        return redirect()->back()->with('status', 'Ürün fire/zayi olarak kaydedildi (stoka aktarılmadı).');
    }
}
