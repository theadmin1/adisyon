<?php

namespace App\Http\Controllers;

use App\Events\KitchenItemStatusUpdated;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\StockMovement;
use App\Services\KitchenDispatchService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        $filterKitchenItems = function ($q) use ($selectedStatus): void {
            $q->routedToKitchen()->whereNotNull('sent_to_kitchen_at');
            if ($selectedStatus !== 'all') {
                if ($selectedStatus === 'cancelled') {
                    $q->where(function ($sub): void {
                        $sub->where('is_cancelled', true)->orWhere('kitchen_status', 'cancelled');
                    });
                } else {
                    $q->where('is_cancelled', false)
                        ->where(function ($sub) use ($selectedStatus) {
                            $sub->where('kitchen_status', $selectedStatus);
                            if ($selectedStatus === 'received') {
                                $sub->orWhere('kitchen_status', 'sent');
                            }
                        });
                }
            }
        };

        $checksQuery = Check::with([
            'diningTable.hall',
            'waiter',
            'items' => function ($query) use ($filterKitchenItems): void {
                $filterKitchenItems($query);
                $query->with('product.category');
            },
        ])
            ->whereHas('items', $filterKitchenItems)
            ->orderBy('id', 'desc');

        $checks = $checksQuery->get();

        $latestOrder = Check::whereHas('items', fn ($query) => $query->routedToKitchen()->whereNotNull('sent_to_kitchen_at'))
            ->latest('kitchen_sent_at')
            ->first();
        $latestKitchenTime = $latestOrder?->kitchen_sent_at ? Carbon::parse($latestOrder->kitchen_sent_at)->toIso8601String() : ($latestOrder?->opened_at ? Carbon::parse($latestOrder->opened_at)->toIso8601String() : '');

        // Kategori Sayaçları
        $stats = [
            'total' => Check::whereHas('items', fn ($query) => $query->routedToKitchen()->whereNotNull('sent_to_kitchen_at'))->count(),
            'received' => CheckItem::routedToKitchen()->whereNotNull('sent_to_kitchen_at')->where('is_cancelled', false)->where(function ($q) {
                $q->whereIn('kitchen_status', ['received', 'sent']);
            })->count(),
            'preparing' => CheckItem::routedToKitchen()->whereNotNull('sent_to_kitchen_at')->where('is_cancelled', false)->where('kitchen_status', 'preparing')->count(),
            'delivered' => CheckItem::routedToKitchen()->whereNotNull('sent_to_kitchen_at')->where('is_cancelled', false)->whereIn('kitchen_status', ['delivered', 'ready', 'served'])->count(),
            'cancelled' => CheckItem::routedToKitchen()->whereNotNull('sent_to_kitchen_at')->where(function ($q) {
                $q->where('is_cancelled', true)->orWhere('kitchen_status', 'cancelled');
            })->count(),
        ];

        return view('kitchen.index', compact('checks', 'stats', 'selectedStatus', 'latestKitchenTime'));
    }

    /**
     * Adisyonu veya eklenen yeni ürünleri Mutfağa Gönderir (İlk Durum: received / Alındı)
     */
    public function sendToKitchen(Request $request, Check $check, KitchenDispatchService $dispatchService): JsonResponse|RedirectResponse
    {
        $result = $dispatchService->send($check);
        $printQueued = $result['print_queued'];
        $printError = $result['print_error'];

        $message = $result['sent_count'] === 0
            ? 'Mutfağa gönderilecek yeni ürün bulunmuyor.'
            : ($printQueued
            ? 'Sipariş mutfağa gönderildi ve mutfak fişi yazıcı sırasına alındı (Durum: ALINDI)!'
            : ($printError
                ? 'Sipariş mutfağa gönderildi ancak mutfak fişi kuyruğa alınamadı: '.$printError
                : 'Sipariş mutfağa gönderildi (Durum: ALINDI).'));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'print_queued' => $printQueued,
                'print_error' => $printError,
                'sent_count' => $result['sent_count'],
                'kitchen_sent_at' => $result['sent_at'],
            ]);
        }

        return redirect()->back()->with('status', $message);
    }

    /**
     * Mutfak personelinin ürün durumunu değiştirmesi (received, preparing, delivered, cancelled)
     */
    public function updateItemStatus(Request $request, CheckItem $item): JsonResponse
    {
        abort_if($item->sent_to_kitchen_at === null || ($item->product && ! $item->product->send_to_kitchen), 404);

        $validated = $request->validate([
            'status' => 'required|string|in:received,sent,preparing,delivered,ready,cancelled',
        ]);

        $status = $validated['status'];
        if ($status === 'sent') {
            $status = 'received';
        }
        if ($status === 'ready') {
            $status = 'delivered';
        }

        $isCancelled = ($status === 'cancelled');

        $item->update([
            'kitchen_status' => $status,
            'is_cancelled' => $isCancelled ? true : $item->is_cancelled,
            'cancelled_at' => $isCancelled ? now() : $item->cancelled_at,
        ]);

        $this->broadcastKitchenItemStatus($item->fresh(['check.diningTable']), $validated['status']);

        if ($isCancelled && $item->product_id) {
            $exists = StockMovement::where('check_item_id', $item->id)->where('type', 'cancellation_pending')->exists();
            if (! $exists) {
                try {
                    $checkExists = $item->check_id ? Check::where('id', $item->check_id)->exists() : false;
                    StockMovement::create([
                        'sync_uuid' => (string) Str::uuid(),
                        'is_synced' => config('database.default') === 'mysql',
                        'product_id' => $item->product_id,
                        'check_id' => $checkExists ? $item->check_id : null,
                        'check_item_id' => $item->id,
                        'type' => 'cancellation_pending',
                        'quantity' => $item->quantity,
                        'status' => 'pending_approval',
                        'notes' => 'Mutfaktan iptal edilen sipariş (Stoka iade onayı bekliyor)',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Stock movement oluşturulamadı: '.$e->getMessage());
                }
            }
        }

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

        foreach ($check->items()->routedToKitchen()->whereNotNull('sent_to_kitchen_at')->get() as $item) {
            $item->update([
                'kitchen_status' => $status,
                'is_cancelled' => $isCancelled ? true : $item->is_cancelled,
                'cancelled_at' => $isCancelled ? now() : $item->cancelled_at,
            ]);

            $this->broadcastKitchenItemStatus($item->fresh(['check.diningTable']), $validated['status']);

            if ($isCancelled && $item->product_id) {
                $exists = StockMovement::where('check_item_id', $item->id)->where('type', 'cancellation_pending')->exists();
                if (! $exists) {
                    try {
                        $checkExists = $item->check_id ? Check::where('id', $item->check_id)->exists() : false;
                        StockMovement::create([
                            'sync_uuid' => (string) Str::uuid(),
                            'is_synced' => config('database.default') === 'mysql',
                            'product_id' => $item->product_id,
                            'check_id' => $checkExists ? $item->check_id : null,
                            'check_item_id' => $item->id,
                            'type' => 'cancellation_pending',
                            'quantity' => $item->quantity,
                            'status' => 'pending_approval',
                            'notes' => 'Mutfaktan toplu iptal edilen sipariş (Stoka iade onayı bekliyor)',
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('Stock movement oluşturulamadı: '.$e->getMessage());
                    }
                }
            }
        }

        $statusName = match ($status) {
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
        $latestIso = null;

        if ($latestOrder && $latestOrder->kitchen_sent_at) {
            $carbon = Carbon::parse($latestOrder->kitchen_sent_at);
            $latestIso = $carbon->toIso8601String();
            if (! $lastTime || $latestIso > $lastTime) {
                $hasNew = true;
            }
        }

        return response()->json([
            'has_new' => $hasNew,
            'latest_time' => $latestIso,
            'table_name' => $latestOrder?->diningTable?->name ?? 'Tezgah',
        ]);
    }

    private function broadcastKitchenItemStatus(CheckItem $item, string $broadcastStatus): void
    {
        if (! in_array($broadcastStatus, ['ready', 'delivered'], true)) {
            return;
        }

        $branchId = (int) $item->branch_id;
        $orderId = (int) $item->check_id;
        if ($branchId <= 0 || $orderId <= 0) {
            return;
        }

        KitchenItemStatusUpdated::dispatch(
            $branchId,
            $orderId,
            (string) ($item->check?->diningTable?->name ?? 'Tezgah'),
            (int) $item->id,
            $broadcastStatus,
        );
    }
}
