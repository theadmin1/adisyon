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
     * ALINDI / HAZIRLANIYOR / TESLİM EDİLDİ / İPTAL kategorileri ile takip
     */
    public function index(Request $request): View
    {
        $selectedStatus = $request->query('status', 'all'); // all, received, preparing, delivered, cancelled

        $checksQuery = Check::whereNotNull('kitchen_sent_at')
            ->with(['diningTable.hall', 'waiter', 'items' => function ($q) use ($selectedStatus) {
                $q->with('product.category');
                if ($selectedStatus !== 'all') {
                    if ($selectedStatus === 'cancelled') {
                        $q->where(function ($sub) {
                            $sub->where('is_cancelled', true)
                                ->orWhere('kitchen_status', 'cancelled');
                        });
                    } else {
                        $q->where('is_cancelled', false)
                          ->where(function ($sub) use ($selectedStatus) {
                              $sub->where('kitchen_status', $selectedStatus);
                              if ($selectedStatus === 'received') {
                                  $sub->orWhere('kitchen_status', 'sent')
                                      ->orWhereNull('kitchen_status');
                              }
                          });
                    }
                }
            }])
            ->whereHas('items', function ($q) use ($selectedStatus) {
                if ($selectedStatus !== 'all') {
                    if ($selectedStatus === 'cancelled') {
                        $q->where('is_cancelled', true)->orWhere('kitchen_status', 'cancelled');
                    } else {
                        $q->where('is_cancelled', false)
                          ->where(function ($sub) use ($selectedStatus) {
                              $sub->where('kitchen_status', $selectedStatus);
                              if ($selectedStatus === 'received') {
                                  $sub->orWhere('kitchen_status', 'sent')
                                      ->orWhereNull('kitchen_status');
                              }
                          });
                    }
                }
            })
            ->orderBy('kitchen_sent_at', 'desc');

        $checks = $checksQuery->get();

        $latestKitchenTime = Check::whereNotNull('kitchen_sent_at')->max('kitchen_sent_at');

        // Kategori Sayaçları
        $stats = [
            'total' => Check::whereNotNull('kitchen_sent_at')->count(),
            'received' => CheckItem::where('is_cancelled', false)->whereIn('kitchen_status', ['received', 'sent', 'pending', null])->count(),
            'preparing' => CheckItem::where('is_cancelled', false)->where('kitchen_status', 'preparing')->count(),
            'delivered' => CheckItem::where('is_cancelled', false)->whereIn('kitchen_status', ['delivered', 'ready', 'served'])->count(),
            'cancelled' => CheckItem::where(function ($q) {
                $q->where('is_cancelled', true)->orWhere('kitchen_status', 'cancelled');
            })->count(),
        ];

        return view('kitchen.index', compact('checks', 'stats', 'selectedStatus', 'latestKitchenTime'));
    }

    /**
     * Adisyonu veya eklenen yeni ürünleri Mutfağa Gönderir (İlk Durum: received / Alındı)
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
                      ->orWhereIn('kitchen_status', ['pending', 'sent']);
                })
                ->update([
                    'kitchen_status' => 'received',
                    'sent_to_kitchen_at' => $now,
                ]);
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Sipariş mutfağa başarıyla gönderildi (Durum: ALINDI)!',
                'kitchen_sent_at' => now()->format('H:i'),
            ]);
        }

        return redirect()->back()->with('status', 'Sipariş mutfağa başarıyla gönderildi!');
    }

    /**
     * Mutfak personelinin ürün durumunu değiştirmesi (received, preparing, delivered, cancelled)
     */
    public function updateItemStatus(Request $request, CheckItem $item): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:received,sent,preparing,delivered,ready,cancelled',
        ]);

        $status = $validated['status'];
        if ($status === 'sent') $status = 'received';
        if ($status === 'ready') $status = 'delivered';

        $isCancelled = ($status === 'cancelled');

        $item->update([
            'kitchen_status' => $status,
            'is_cancelled' => $isCancelled ? true : $item->is_cancelled,
            'cancelled_at' => $isCancelled ? now() : $item->cancelled_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sipariş durumu güncellendi.',
            'status' => $item->kitchen_status,
        ]);
    }

    /**
     * Masadaki tüm mutfak siparişlerinin durumunu toplu değiştirme (ALINDI, HAZIRLANIYOR, TESLİM EDİLDİ, İPTAL)
     */
    public function updateCheckKitchenStatus(Request $request, Check $check): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:received,preparing,delivered,cancelled',
        ]);

        $status = $validated['status'];
        $isCancelled = ($status === 'cancelled');

        $check->items()
            ->update([
                'kitchen_status' => $status,
                'is_cancelled' => $isCancelled ? true : DB::raw('is_cancelled'),
                'cancelled_at' => $isCancelled ? now() : DB::raw('cancelled_at'),
            ]);

        $statusName = match($status) {
            'received' => 'ALINDI',
            'preparing' => 'HAZIRLANIYOR',
            'delivered' => 'TESLİM EDİLDİ',
            'cancelled' => 'İPTAL EDİLDİ',
        };

        return response()->json([
            'success' => true,
            'message' => "Masa #{$check->diningTable?->name} tüm siparişleri '{$statusName}' yapıldı.",
        ]);
    }

    /**
     * Mutfak Ekranı için Anlık Canlı Bildirim ve Polling Servisi
     */
    public function poll(Request $request): JsonResponse
    {
        $lastTime = $request->query('last_time');

        $latestOrder = Check::whereNotNull('kitchen_sent_at')
            ->orderBy('kitchen_sent_at', 'desc')
            ->first();

        $hasNew = false;
        if ($latestOrder && $latestOrder->kitchen_sent_at) {
            $latestIso = $latestOrder->kitchen_sent_at->toIso8601String();
            if (!$lastTime || $latestIso > $lastTime) {
                $hasNew = true;
            }
        }

        return response()->json([
            'has_new' => $hasNew,
            'latest_time' => $latestOrder?->kitchen_sent_at?->toIso8601String(),
            'table_name' => $latestOrder?->diningTable?->name ?? 'Tezgah',
        ]);
    }
}
