<?php

namespace App\Http\Controllers\Api\Waiter;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWaiterApiToken;
use App\Models\CheckItem;
use App\Services\AuditLogger;
use App\Support\WaiterApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    public function updates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:received,preparing,ready,delivered,served,cancelled,all'],
            'mine' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        $token = $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE);
        $staff = $request->attributes->get(EnsureWaiterApiToken::STAFF_ATTRIBUTE);
        $status = $validated['status'] ?? 'all';
        $items = CheckItem::withoutGlobalScopes()
            ->where('branch_id', $token->branch_id)
            ->routedToKitchen()
            ->whereNotNull('sent_to_kitchen_at')
            ->when($validated['since'] ?? null, fn ($query, $since) => $query->where('updated_at', '>', $since))
            ->when($status !== 'all', function ($query) use ($status): void {
                if ($status === 'ready') {
                    $query->whereIn('kitchen_status', ['ready', 'delivered']);
                } else {
                    $query->where('kitchen_status', $status);
                }
            })
            ->when($request->boolean('mine'), fn ($query) => $query->whereHas('check', fn ($check) => $check
                ->withoutGlobalScopes()
                ->where('branch_id', $token->branch_id)
                ->where('waiter_staff_profile_id', $staff->id)))
            ->with(['check' => fn ($query) => $query->withoutGlobalScopes()->with('diningTable.hall')])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($validated['limit'] ?? 100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items->map(fn (CheckItem $item): array => [
                ...WaiterApiPresenter::item($item),
                'order' => $item->check ? WaiterApiPresenter::orderSummary($item->check) : null,
            ]),
            'meta' => [
                'server_time' => now()->toIso8601String(),
                'poll_after_seconds' => 4,
            ],
        ]);
    }

    public function markServed(Request $request, int $item, AuditLogger $auditLogger): JsonResponse
    {
        $token = $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE);
        $checkItem = CheckItem::withoutGlobalScopes()
            ->where('branch_id', $token->branch_id)
            ->routedToKitchen()
            ->where('is_cancelled', false)
            ->whereNotNull('sent_to_kitchen_at')
            ->findOrFail($item);

        if (! in_array($checkItem->kitchen_status, ['ready', 'delivered', 'served'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Ürün henüz servise hazır değil.',
            ], 409);
        }

        $checkItem->update([
            'kitchen_status' => 'served',
            'is_synced' => config('database.default') === 'mysql',
        ]);
        $auditLogger->record(
            'waiter_api.item_served',
            $checkItem->check,
            [],
            ['item_id' => $checkItem->id, 'product_name' => $checkItem->product_name],
            'Mobil garson uygulaması ürünün servis edildiğini işaretledi.',
            'waiter'
        );

        return response()->json([
            'success' => true,
            'message' => 'Ürün servis edildi olarak işaretlendi.',
            'data' => WaiterApiPresenter::item($checkItem->fresh()),
        ]);
    }
}
