<?php

namespace App\Http\Controllers\Api\Waiter;

use App\Enums\CheckStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWaiterApiToken;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Services\TableLockService;
use App\Support\WaiterApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function halls(Request $request, TableLockService $tableLockService): JsonResponse
    {
        $branchId = $this->branchId($request);
        $halls = Hall::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->with(['tables' => fn ($query) => $query
                ->withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->with(['checks' => $this->activeOrderQuery()])
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $halls->map(fn (Hall $hall): array => [
                'id' => $hall->id,
                'name' => $hall->name,
                'code' => $hall->code,
                'tables' => $hall->tables->map(
                    fn (DiningTable $table): array => WaiterApiPresenter::table($table, $tableLockService->stateForTable($table))
                )->values(),
            ]),
        ]);
    }

    public function index(Request $request, TableLockService $tableLockService): JsonResponse
    {
        $branchId = $this->branchId($request);
        $validated = $request->validate([
            'hall_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:available,occupied,awaiting_payment,reserved'],
        ]);
        $tables = DiningTable::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->when($validated['hall_id'] ?? null, fn ($query, $hallId) => $query->where('hall_id', $hallId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with(['hall', 'checks' => $this->activeOrderQuery()])
            ->orderBy('hall_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tables->map(fn (DiningTable $table): array => [
                ...WaiterApiPresenter::table($table, $tableLockService->stateForTable($table)),
                'hall_name' => $table->hall?->name,
            ]),
        ]);
    }

    public function show(Request $request, int $table, TableLockService $tableLockService): JsonResponse
    {
        $branchId = $this->branchId($request);
        $diningTable = DiningTable::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->with([
                'hall',
                'checks' => fn ($query) => $this->activeOrderQuery()($query)
                    ->with(['items' => fn ($items) => $items->where('is_cancelled', false)->orderBy('id'), 'payments']),
            ])
            ->findOrFail($table);
        $activeOrder = $diningTable->checks->first();

        return response()->json([
            'success' => true,
            'data' => [
                ...WaiterApiPresenter::table($diningTable, $tableLockService->stateForTable($diningTable)),
                'hall_name' => $diningTable->hall?->name,
                'active_order' => $activeOrder ? WaiterApiPresenter::order($activeOrder) : null,
            ],
        ]);
    }

    private function branchId(Request $request): int
    {
        return (int) $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE)->branch_id;
    }

    private function activeOrderQuery(): \Closure
    {
        return static fn ($query) => $query
            ->withoutGlobalScopes()
            ->whereIn('status', [CheckStatus::Open->value, CheckStatus::AwaitingPayment->value])
            ->withSum('payments as paid_total', 'amount')
            ->latest('opened_at')
            ->limit(1);
    }
}
