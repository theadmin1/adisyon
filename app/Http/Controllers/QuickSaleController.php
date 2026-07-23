<?php

namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Check;
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

        return view('quicksale.index', compact('categories', 'products'));
    }

    public function store(Request $request, CheckService $checkService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:nakit,kredi_karti,yemek_karti',
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

            // Ürün kalemlerini adisyona ekle ve güncellenmiş adisyonu al
            $check = $checkService->addItems($check, $validated['items']);

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
}
