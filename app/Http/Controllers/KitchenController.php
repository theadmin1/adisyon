<?php

namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Models\Check;
use App\Models\CheckItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KitchenController extends Controller
{
    /**
     * Mutfak Ekranı (Kitchen Display System - KDS)
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'active'); // active, completed, all

        $checksQuery = Check::whereIn('status', [CheckStatus::Open, CheckStatus::AwaitingPayment])
            ->whereNotNull('kitchen_sent_at')
            ->with(['diningTable.hall', 'waiter', 'items' => function ($q) {
                $q->where('is_cancelled', false)->with('product.category');
            }])
            ->orderBy('kitchen_sent_at', 'asc');

        if ($status === 'completed') {
            $checksQuery = Check::whereNotNull('kitchen_sent_at')
                ->whereDoesntHave('items', function ($q) {
                    $q->where('is_cancelled', false)
                      ->whereIn('kitchen_status', ['sent', 'preparing']);
                })
                ->with(['diningTable.hall', 'waiter', 'items' => function ($q) {
                    $q->where('is_cancelled', false)->with('product.category');
                }])
                ->orderBy('updated_at', 'desc')
                ->take(20);
        }

        $checks = $checksQuery->get();

        $stats = [
            'total_kitchen_orders' => Check::whereNotNull('kitchen_sent_at')->whereIn('status', [CheckStatus::Open, CheckStatus::AwaitingPayment])->count(),
            'pending_items' => CheckItem::where('is_cancelled', false)->whereIn('kitchen_status', ['sent', 'preparing'])->count(),
            'ready_items' => CheckItem::where('is_cancelled', false)->where('kitchen_status', 'ready')->count(),
        ];

        return view('kitchen.index', compact('checks', 'stats', 'status'));
    }

    /**
     * Adisyonu veya eklenen yeni ürünleri Mutfağa Gönderir
     */
    public function sendToKitchen(Request $request, Check $check): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($check) {
            $now = now();

            $check->update([
                'kitchen_sent_at' => $now,
            ]);

            $check->items()
                ->where('is_cancelled', false)
                ->where(function ($q) {
                    $q->whereNull('sent_to_kitchen_at')
                      ->orWhere('kitchen_status', 'pending');
                })
                ->update([
                    'kitchen_status' => 'sent',
                    'sent_to_kitchen_at' => $now,
                ]);
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Sipariş mutfağa başarıyla gönderildi!',
                'kitchen_sent_at' => now()->format('H:i'),
            ]);
        }

        return redirect()->back()->with('status', 'Sipariş mutfağa başarıyla gönderildi!');
    }

    /**
     * Mutfak personelinin ürün durumunu değiştirmesi (sent -> preparing -> ready -> served)
     */
    public function updateItemStatus(Request $request, CheckItem $item): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:sent,preparing,ready,served',
        ]);

        $item->update([
            'kitchen_status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ürün mutfak durumu güncellendi.',
            'status' => $item->kitchen_status,
        ]);
    }

    /**
     * Masadaki tüm mutfak siparişlerini "Hazır / Tamamlandı" olarak işaretleme
     */
    public function completeCheckKitchen(Request $request, Check $check): JsonResponse
    {
        $check->items()
            ->where('is_cancelled', false)
            ->update([
                'kitchen_status' => 'ready',
            ]);

        return response()->json([
            'success' => true,
            'message' => "Masa #{$check->diningTable?->name} siparişleri hazır olarak işaretlendi.",
        ]);
    }
}
