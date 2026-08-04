<?php

namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Enums\TableStatus;
use App\Http\Requests\StoreDiningTableRequest;
use App\Http\Requests\UpdateDiningTableRequest;
use App\Models\Category;
use App\Models\Check;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Services\AutoSyncService;
use App\Services\TableLockService;
use App\Support\PaymentMethods;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiningTableController extends Controller
{
    public function state(Request $request, DiningTable $table, TableLockService $tableLockService): JsonResponse
    {
        $check = Check::query()
            ->where('dining_table_id', $table->id)
            ->whereIn('status', ['open', 'awaiting_payment'])
            ->with(['items' => fn ($query) => $query
                ->select('id', 'check_id', 'quantity', 'total_price', 'added_by_name', 'is_cancelled', 'updated_at')
                ->orderBy('id')])
            ->latest('id')
            ->first();

        $lock = $tableLockService->get($table);
        $currentActorId = $this->currentTableEditorActorId($request);
        $currentActorName = $this->currentTableEditorActorName($request);

        return response()->json([
            'signature' => $this->checkSignature($check),
            'has_qr_order' => $check?->items->contains(fn ($item) => $item->added_by_name === 'QR Menu') ?? false,
            'editor_lock' => [
                'is_locked' => $lock !== null,
                'locked_by' => $lock['locked_by'] ?? null,
                'actor_name' => $lock['actor_name'] ?? null,
                'conflict' => $lock !== null && ! $tableLockService->isOwnedByCurrentActor($lock, 'cashier', $currentActorId, $currentActorName),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function acquireEditorLock(Request $request, DiningTable $table, TableLockService $tableLockService): JsonResponse
    {
        $actorId = $this->currentTableEditorActorId($request);
        $actorName = $this->currentTableEditorActorName($request);
        $lock = $tableLockService->acquire($table, 'cashier', $actorId, $actorName);

        if (($lock['conflict'] ?? false) === true) {
            return response()->json([
                'success' => false,
                'message' => trim(($lock['actor_name'] ?? 'Baska bir personel').' bu masada islem yapiyor.'),
                'code' => 'TABLE_IN_USE',
                'data' => [
                    'table_id' => (int) $table->id,
                    'actor_name' => $lock['actor_name'] ?? null,
                    'locked_by' => $lock['locked_by'] ?? null,
                ],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Masa duzenleme kilidi aktif.',
            'data' => [
                'table_id' => (int) $table->id,
                'actor_name' => $lock['actor_name'] ?? null,
                'locked_by' => $lock['locked_by'] ?? null,
            ],
        ]);
    }

    public function releaseEditorLock(Request $request, DiningTable $table, TableLockService $tableLockService): JsonResponse
    {
        $tableLockService->releaseIfOwnedBy(
            $table,
            'cashier',
            $this->currentTableEditorActorId($request),
            $this->currentTableEditorActorName($request),
        );

        return response()->json([
            'success' => true,
            'message' => 'Masa duzenleme kilidi kaldirildi.',
        ]);
    }

    public function index(Request $request): View
    {
        AutoSyncService::syncIfLocal();
        $statusFilter = $request->string('status')->toString();
        $tables = DiningTable::query()
            ->with([
                'hall',
                'checks' => fn ($query) => $query
                    ->whereIn('status', ['open', 'awaiting_payment'])
                    ->withCount('items')
                    ->latest(),
            ])
            ->when($request->filled('hall'), fn ($query) => $query->where('hall_id', $request->integer('hall')))
            ->when($statusFilter === 'occupied', fn ($query) => $query
                ->whereHas('checks', fn ($checkQuery) => $checkQuery->where('status', 'open')))
            ->when($statusFilter === 'awaiting_payment', fn ($query) => $query
                ->whereHas('checks', fn ($checkQuery) => $checkQuery->where('status', 'awaiting_payment')))
            ->when($statusFilter === 'available', fn ($query) => $query
                ->whereNotIn('status', ['reserved', 'inactive'])
                ->whereDoesntHave('checks', fn ($checkQuery) => $checkQuery
                    ->whereIn('status', ['open', 'awaiting_payment'])))
            ->when($statusFilter === 'reserved', fn ($query) => $query
                ->where('status', 'reserved')
                ->whereDoesntHave('checks', fn ($checkQuery) => $checkQuery
                    ->whereIn('status', ['open', 'awaiting_payment'])))
            ->orderBy('hall_id')
            ->orderBy('name')
            ->get();

        $halls = Hall::query()->where('is_active', true)->orderBy('sort_order')->get();
        $totalTables = DiningTable::count();
        $awaitingCount = DiningTable::query()
            ->whereHas('checks', fn ($query) => $query->where('status', 'awaiting_payment'))
            ->count();
        $occupiedCount = DiningTable::query()
            ->whereHas('checks', fn ($query) => $query->where('status', 'open'))
            ->whereDoesntHave('checks', fn ($query) => $query->where('status', 'awaiting_payment'))
            ->count();
        $availableCount = DiningTable::query()
            ->whereNotIn('status', ['reserved', 'inactive'])
            ->whereDoesntHave('checks', fn ($query) => $query
                ->whereIn('status', ['open', 'awaiting_payment']))
            ->count();
        $openRevenue = Check::whereIn('status', ['open', 'awaiting_payment'])->sum('total');

        $stats = [
            'total_tables' => $totalTables,
            'occupied_tables' => $occupiedCount,
            'available_tables' => $availableCount,
            'awaiting_tables' => $awaitingCount,
            'occupancy_rate' => $totalTables > 0
                ? round((($occupiedCount + $awaitingCount) / $totalTables) * 100)
                : 0,
            'open_revenue' => number_format($openRevenue, 2),
        ];

        return view('tables.index', [
            'tables' => $tables,
            'groupedTables' => $tables->groupBy(fn ($table) => $table->hall?->name ?: 'Salonsuz Alan'),
            'halls' => $halls,
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, DiningTable $table): View
    {
        AutoSyncService::syncIfLocal();
        $table->load(['hall']);

        $activeCheck = Check::query()
            ->where('dining_table_id', $table->id)
            ->whereIn('status', ['open', 'awaiting_payment'])
            ->with(['items' => fn ($query) => $query->with('product')->where('is_cancelled', false)->orderBy('id', 'asc'), 'payments'])
            ->latest()
            ->first();

        $siblingChecks = $activeCheck
            ? Check::query()
                ->where('dining_table_id', $table->id)
                ->whereIn('status', [CheckStatus::Open, CheckStatus::AwaitingPayment])
                ->whereKeyNot($activeCheck->id)
                ->get()
            : collect();

        $categories = Category::query()
            ->with(['products' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->get();

        $allTables = DiningTable::query()
            ->where('is_active', true)
            ->with(['hall', 'checks' => fn ($query) => $query->whereIn('status', ['open', 'awaiting_payment'])])
            ->orderBy('hall_id')
            ->orderBy('name')
            ->get();

        $moveTargets = $allTables->where('id', '!=', $table->id);
        $checkSignature = $this->checkSignature($activeCheck);

        return view('tables.show', [
            'table' => $table,
            'activeCheck' => $activeCheck,
            'siblingChecks' => $siblingChecks,
            'categories' => $categories,
            'moveTargets' => $moveTargets,
            'allTables' => $allTables,
            'paymentMethods' => PaymentMethods::active((int) $request->user()->branch_id),
            'checkSignature' => $checkSignature,
        ]);
    }

    public function store(StoreDiningTableRequest $request): RedirectResponse
    {
        DiningTable::create([
            'hall_id' => $request->integer('hall_id') ?: null,
            'name' => $request->string('name')->toString(),
            'code' => $request->filled('code') ? $request->string('code')->trim()->toString() : null,
            'capacity' => $request->integer('capacity'),
            'status' => 'available',
            'is_active' => true,
            'notes' => $request->string('notes')->toString(),
        ]);

        AutoSyncService::syncIfLocal();

        return back()->with('status', 'Masa kaydi olusturuldu.');
    }

    public function update(UpdateDiningTableRequest $request, DiningTable $table): RedirectResponse
    {
        $hallId = $request->integer('hall_id') ?: null;
        $activeCheckStatuses = $table->checks()
            ->whereIn('status', [CheckStatus::Open->value, CheckStatus::AwaitingPayment->value])
            ->pluck('status');
        $hasActiveCheck = $activeCheckStatuses->isNotEmpty();
        $isActive = $request->boolean('is_active');

        if (! $isActive && $hasActiveCheck) {
            return back()->withErrors([
                'table' => 'Acik adisyonu bulunan masa pasife alinamaz.',
            ])->withInput();
        }

        $requestedStatus = $request->string('status')->toString();
        if ($hasActiveCheck) {
            $status = $activeCheckStatuses->contains(CheckStatus::AwaitingPayment->value)
                ? TableStatus::AwaitingPayment->value
                : TableStatus::Occupied->value;
        } else {
            $status = in_array($requestedStatus, [TableStatus::Available->value, TableStatus::Reserved->value], true)
                ? $requestedStatus
                : TableStatus::Available->value;
        }

        if (! $isActive) {
            $status = TableStatus::Inactive->value;
        }

        $table->update([
            'hall_id' => $hallId,
            'name' => $request->string('name')->toString(),
            'code' => $request->filled('code') ? $request->string('code')->trim()->toString() : null,
            'capacity' => $request->integer('capacity'),
            'status' => $status,
            'is_active' => $isActive,
            'notes' => $request->string('notes')->toString() ?: null,
        ]);

        AutoSyncService::syncIfLocal();

        return back()->with('status', 'Masa guncellendi.');
    }

    public function destroy(DiningTable $table): RedirectResponse
    {
        $hasOpenCheck = $table->checks()
            ->whereIn('status', [CheckStatus::Open->value, CheckStatus::AwaitingPayment->value])
            ->exists();

        if ($hasOpenCheck) {
            return back()->withErrors([
                'table' => 'Masada acik adisyon var. Once adisyonu kapatin.',
            ]);
        }

        $table->delete();

        AutoSyncService::syncIfLocal();

        return redirect()->route('tables.index')->with('status', 'Masa silindi.');
    }

    private function checkSignature(?Check $check): string
    {
        if (! $check) {
            return 'empty';
        }

        return hash('sha256', json_encode([
            'id' => $check->id,
            'status' => $check->status->value,
            'updated_at' => $check->updated_at?->format('Y-m-d H:i:s.u'),
            'items' => $check->items->map(fn ($item) => [
                $item->id,
                (string) $item->quantity,
                (string) $item->total_price,
                $item->added_by_name,
                $item->is_cancelled,
                $item->updated_at?->format('Y-m-d H:i:s.u'),
            ])->values()->all(),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function currentTableEditorActorId(Request $request): ?int
    {
        $staffId = $request->session()->get('active_staff_id');

        if (is_numeric($staffId)) {
            return (int) $staffId;
        }

        return $request->user()?->id ? (int) $request->user()->id : null;
    }

    private function currentTableEditorActorName(Request $request): string
    {
        return (string) ($request->session()->get('active_staff_name') ?: $request->user()?->name ?: 'Personel');
    }
}
