<?php

namespace App\Http\Controllers\Api\Waiter;

use App\Enums\CheckStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWaiterApiToken;
use App\Models\Check;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\AutoSyncService;
use App\Services\Checks\CheckService;
use App\Support\PaymentMethods;
use App\Support\WaiterApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(Request $request, int $order): JsonResponse
    {
        $check = $this->findOrder($request, $order)->load(['payments' => fn ($query) => $query->orderBy('id')]);
        $paid = (float) $check->payments->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $check->id,
                'total' => (float) $check->total,
                'paid' => $paid,
                'remaining' => max(0, round((float) $check->total - $paid, 2)),
                'payments' => $check->payments->map(fn (Payment $payment): array => WaiterApiPresenter::payment($payment))->values(),
            ],
        ]);
    }

    public function store(
        Request $request,
        int $order,
        CheckService $checkService,
        AuditLogger $auditLogger
    ): JsonResponse {
        $validated = $request->validate([
            'client_reference' => ['required', 'uuid'],
            'method' => ['required', 'string', Rule::in(PaymentMethods::activeIds($this->branchId($request)))],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);
        $branchId = $this->branchId($request);
        $existing = Payment::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('client_reference', $validated['client_reference'])
            ->first();
        if ($existing) {
            if ((int) $existing->check_id !== $order) {
                throw ValidationException::withMessages([
                    'client_reference' => ['Bu ödeme referansı başka bir adisyonda kullanılmış.'],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->paymentResult($this->findOrder($request, $order), $existing),
                'meta' => ['idempotent_replay' => true],
            ]);
        }

        [$check, $payment] = DB::transaction(function () use ($request, $order, $branchId, $validated, $checkService): array {
            $check = Check::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->whereIn('status', [CheckStatus::Open->value, CheckStatus::AwaitingPayment->value])
                ->lockForUpdate()
                ->findOrFail($order);
            $paid = (float) Payment::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('check_id', $check->id)
                ->sum('amount');
            $remaining = max(0, round((float) $check->total - $paid, 2));
            if ($remaining <= 0) {
                throw ValidationException::withMessages(['amount' => ['Bu adisyonun kalan bakiyesi yok.']]);
            }
            $amount = isset($validated['amount']) ? round((float) $validated['amount'], 2) : $remaining;
            if ($amount > $remaining + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => ["Ödeme kalan {$remaining} tutarını aşamaz."],
                ]);
            }
            $payment = Payment::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'check_id' => $check->id,
                'payment_method' => $validated['method'],
                'client_reference' => $validated['client_reference'],
                'amount' => $amount,
                'sync_uuid' => (string) Str::uuid(),
                'is_synced' => config('database.default') === 'mysql',
            ]);
            if ($amount >= $remaining - 0.01) {
                $checkService->closeCheck($check, $request->user());
            } else {
                $check->update([
                    'status' => CheckStatus::AwaitingPayment,
                    'is_synced' => config('database.default') === 'mysql',
                ]);
            }

            return [$check->fresh(), $payment];
        });
        AutoSyncService::syncIfLocal();
        $auditLogger->record(
            $check->status === CheckStatus::Closed ? 'waiter_api.order_paid' : 'waiter_api.partial_payment',
            $check,
            [],
            ['payment_id' => $payment->id, 'method' => $payment->payment_method, 'amount' => $payment->amount],
            'Mobil garson uygulaması ödeme aldı.',
            'payments'
        );

        return response()->json([
            'success' => true,
            'message' => $check->status === CheckStatus::Closed ? 'Ödeme tamamlandı ve adisyon kapatıldı.' : 'Kısmi ödeme alındı.',
            'data' => $this->paymentResult($check, $payment),
        ], 201);
    }

    private function paymentResult(Check $check, Payment $payment): array
    {
        $check = $check->fresh()->load(['diningTable.hall', 'items', 'payments']);

        return [
            'payment' => WaiterApiPresenter::payment($payment),
            'order' => WaiterApiPresenter::order($check),
        ];
    }

    private function findOrder(Request $request, int $order): Check
    {
        return Check::withoutGlobalScopes()
            ->where('branch_id', $this->branchId($request))
            ->findOrFail($order);
    }

    private function branchId(Request $request): int
    {
        return (int) $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE)->branch_id;
    }
}
