<?php

namespace App\Http\Controllers\Api\Waiter;

use App\Enums\CheckStatus;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWaiterApiToken;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\StaffProfile;
use App\Services\AuditLogger;
use App\Services\Checks\CheckService;
use App\Services\KitchenDispatchService;
use App\Services\TableLockService;
use App\Support\WaiterApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:open,awaiting_payment,closed,cancelled,active'],
            'scope' => ['nullable', 'string', 'in:all,mine'],
            'updated_after' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $status = $validated['status'] ?? 'active';
        $staff = $this->staff($request);
        $orders = Check::withoutGlobalScopes()
            ->where('branch_id', $this->branchId($request))
            ->when($status === 'active', fn ($query) => $query->whereIn('status', [CheckStatus::Open->value, CheckStatus::AwaitingPayment->value]))
            ->when($status !== 'active', fn ($query) => $query->where('status', $status))
            ->when(($validated['scope'] ?? 'all') === 'mine', fn ($query) => $query->where('waiter_staff_profile_id', $staff->id))
            ->when($validated['updated_after'] ?? null, fn ($query, $after) => $query->where('updated_at', '>', $after))
            ->with(['diningTable.hall'])
            ->withSum('payments as paid_total', 'amount')
            ->latest('opened_at')
            ->limit($validated['limit'] ?? 50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn (Check $order): array => WaiterApiPresenter::orderSummary($order)),
            'meta' => ['server_time' => now()->toIso8601String()],
        ]);
    }

    public function store(
        Request $request,
        CheckService $checkService,
        AuditLogger $auditLogger,
        TableLockService $tableLockService,
    ): JsonResponse {
        $validated = $request->validate([
            'client_reference' => ['required', 'uuid'],
            'dining_table_id' => ['required', 'integer'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.product_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01', 'max:999'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);
        $branchId = $this->branchId($request);
        $existing = Check::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('client_reference', $validated['client_reference'])
            ->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => WaiterApiPresenter::order($this->loadOrder($existing)),
                'meta' => ['idempotent_replay' => true],
            ]);
        }

        $staff = $this->staff($request);
        $this->validateProducts($branchId, $validated['items'] ?? []);

        try {
            $order = DB::transaction(function () use ($validated, $branchId, $request, $staff, $checkService, $tableLockService): Check {
                $table = DiningTable::withoutGlobalScopes()
                    ->where('branch_id', $branchId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($validated['dining_table_id']);
                $tableLockService->ensureUnlocked($table);
                $hasActiveOrder = Check::withoutGlobalScopes()
                    ->where('branch_id', $branchId)
                    ->where('dining_table_id', $table->id)
                    ->whereIn('status', [CheckStatus::Open->value, CheckStatus::AwaitingPayment->value])
                    ->exists();
                if ($hasActiveOrder) {
                    throw ValidationException::withMessages([
                        'dining_table_id' => ['Bu masada zaten açık bir adisyon var.'],
                    ]);
                }

                $order = $checkService->openCheck($table, $request->user(), [
                    'guest_count' => $validated['guest_count'] ?? 1,
                    'client_reference' => $validated['client_reference'],
                ]);
                $order->update([
                    'waiter_staff_profile_id' => $staff->id,
                    'waiter_name' => $staff->name,
                    'customer_notes' => $validated['customer_notes'] ?? null,
                    'is_synced' => config('database.default') === 'mysql',
                ]);
                if (($validated['items'] ?? []) !== []) {
                    $checkService->addItems($order, $validated['items'], $staff);
                }

                return $order;
            });
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['items' => [$exception->getMessage()]]);
        }

        $auditLogger->record(
            'waiter_api.order_opened',
            $order,
            [],
            ['table_id' => $order->dining_table_id, 'guest_count' => $order->guest_count],
            'Mobil garson uygulaması adisyon açtı.',
            'waiter'
        );

        return response()->json([
            'success' => true,
            'message' => 'Adisyon açıldı.',
            'data' => WaiterApiPresenter::order($this->loadOrder($order)),
        ], 201);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => WaiterApiPresenter::order($this->loadOrder($this->findOrder($request, $order))),
        ]);
    }

    public function addItems(
        Request $request,
        int $order,
        CheckService $checkService,
        AuditLogger $auditLogger,
        TableLockService $tableLockService,
    ): JsonResponse {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);
        $check = $this->activeOrder($request, $order);
        $this->ensureOrderTableUnlocked($check, $tableLockService);
        $this->validateProducts($this->branchId($request), $validated['items']);
        $staff = $this->staff($request);

        try {
            if ($check->status === CheckStatus::AwaitingPayment) {
                $check->update(['status' => CheckStatus::Open, 'is_synced' => config('database.default') === 'mysql']);
                $check->diningTable?->update(['status' => TableStatus::Occupied]);
            }
            if (! $check->waiter_staff_profile_id) {
                $check->update([
                    'waiter_staff_profile_id' => $staff->id,
                    'waiter_name' => $staff->name,
                    'is_synced' => config('database.default') === 'mysql',
                ]);
            }
            $checkService->addItems($check, $validated['items'], $staff);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['items' => [$exception->getMessage()]]);
        }

        $auditLogger->record(
            'waiter_api.items_added',
            $check,
            [],
            ['item_count' => count($validated['items'])],
            'Mobil garson uygulaması adisyona ürün ekledi.',
            'waiter'
        );

        return response()->json([
            'success' => true,
            'message' => 'Ürünler adisyona eklendi.',
            'data' => WaiterApiPresenter::order($this->loadOrder($check)),
        ]);
    }

    public function removeItem(
        Request $request,
        int $order,
        int $item,
        CheckService $checkService,
        AuditLogger $auditLogger,
        TableLockService $tableLockService,
    ): JsonResponse {
        $check = $this->activeOrder($request, $order);
        $this->ensureOrderTableUnlocked($check, $tableLockService);
        $checkItem = CheckItem::withoutGlobalScopes()
            ->where('branch_id', $this->branchId($request))
            ->where('check_id', $check->id)
            ->where('is_cancelled', false)
            ->findOrFail($item);
        if ($checkItem->sent_to_kitchen_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Mutfağa gönderilen ürün mobil uygulamadan silinemez.',
            ], 409);
        }

        $checkService->removeItem($checkItem);
        $auditLogger->record(
            'waiter_api.item_removed',
            $check,
            ['item_id' => $checkItem->id, 'product_name' => $checkItem->product_name],
            [],
            'Mobil garson uygulaması adisyon kalemini kaldırdı.',
            'waiter'
        );

        return response()->json([
            'success' => true,
            'message' => 'Ürün adisyondan kaldırıldı.',
            'data' => WaiterApiPresenter::order($this->loadOrder($check)),
        ]);
    }

    public function updateNotes(Request $request, int $order, AuditLogger $auditLogger, TableLockService $tableLockService): JsonResponse
    {
        $validated = $request->validate(['customer_notes' => ['nullable', 'string', 'max:1000']]);
        $check = $this->activeOrder($request, $order);
        $this->ensureOrderTableUnlocked($check, $tableLockService);
        $oldNotes = $check->customer_notes;
        $check->update([
            'customer_notes' => $validated['customer_notes'] ?? null,
            'is_synced' => config('database.default') === 'mysql',
        ]);
        $auditLogger->record(
            'waiter_api.notes_updated',
            $check,
            ['customer_notes' => $oldNotes],
            ['customer_notes' => $check->customer_notes],
            'Mobil garson uygulaması müşteri notunu güncelledi.',
            'waiter'
        );

        return response()->json([
            'success' => true,
            'message' => 'Müşteri notu güncellendi.',
            'data' => WaiterApiPresenter::order($this->loadOrder($check)),
        ]);
    }

    public function sendToKitchen(
        Request $request,
        int $order,
        KitchenDispatchService $dispatchService,
        AuditLogger $auditLogger,
        TableLockService $tableLockService,
    ): JsonResponse {
        $check = $this->activeOrder($request, $order);
        $this->ensureOrderTableUnlocked($check, $tableLockService);
        if (! $check->items()->where('is_cancelled', false)->whereNull('sent_to_kitchen_at')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Mutfağa gönderilecek yeni ürün yok.',
            ], 409);
        }
        $result = $dispatchService->send($check);
        $auditLogger->record(
            'waiter_api.sent_to_kitchen',
            $check,
            [],
            ['sent_count' => $result['sent_count'], 'print_queued' => $result['print_queued']],
            'Mobil garson uygulaması siparişi mutfağa gönderdi.',
            'waiter'
        );

        return response()->json([
            'success' => true,
            'message' => 'Sipariş mutfağa gönderildi.',
            'data' => [
                ...$result,
                'order' => WaiterApiPresenter::order($this->loadOrder($check)),
            ],
        ]);
    }

    public function requestPayment(Request $request, int $order, AuditLogger $auditLogger, TableLockService $tableLockService): JsonResponse
    {
        $check = $this->activeOrder($request, $order);
        $this->ensureOrderTableUnlocked($check, $tableLockService);
        if (! $check->items()->where('is_cancelled', false)->exists()) {
            throw ValidationException::withMessages([
                'order' => ['Ürün bulunmayan bir adisyon için hesap istenemez.'],
            ]);
        }
        $check->update([
            'status' => CheckStatus::AwaitingPayment,
            'is_synced' => config('database.default') === 'mysql',
        ]);
        $check->diningTable?->update(['status' => TableStatus::AwaitingPayment]);
        $auditLogger->record(
            'waiter_api.payment_requested',
            $check,
            [],
            ['status' => CheckStatus::AwaitingPayment->value],
            'Mobil garson uygulaması hesap istedi.',
            'waiter'
        );

        return response()->json([
            'success' => true,
            'message' => 'Hesap isteği oluşturuldu.',
            'data' => WaiterApiPresenter::order($this->loadOrder($check)),
        ]);
    }

    private function validateProducts(int $branchId, array $items): void
    {
        if ($items === []) {
            return;
        }
        $ids = collect($items)->pluck('product_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $validCount = Product::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->count();
        if ($validCount !== $ids->count()) {
            throw ValidationException::withMessages([
                'items' => ['Ürünlerden biri bu şubede satışa açık değil.'],
            ]);
        }
    }

    private function findOrder(Request $request, int $order): Check
    {
        return Check::withoutGlobalScopes()
            ->where('branch_id', $this->branchId($request))
            ->findOrFail($order);
    }

    private function activeOrder(Request $request, int $order): Check
    {
        return Check::withoutGlobalScopes()
            ->where('branch_id', $this->branchId($request))
            ->whereIn('status', [CheckStatus::Open->value, CheckStatus::AwaitingPayment->value])
            ->findOrFail($order);
    }

    private function loadOrder(Check $order): Check
    {
        return $order->fresh()->load([
            'diningTable.hall',
            'items' => fn ($query) => $query->where('is_cancelled', false)->orderBy('id'),
            'payments' => fn ($query) => $query->orderBy('id'),
        ]);
    }

    private function branchId(Request $request): int
    {
        return (int) $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE)->branch_id;
    }

    private function staff(Request $request): StaffProfile
    {
        return $request->attributes->get(EnsureWaiterApiToken::STAFF_ATTRIBUTE);
    }

    private function ensureOrderTableUnlocked(Check $check, TableLockService $tableLockService): void
    {
        if ($check->dining_table_id) {
            $tableLockService->ensureUnlocked((int) $check->dining_table_id);
        }
    }
}
