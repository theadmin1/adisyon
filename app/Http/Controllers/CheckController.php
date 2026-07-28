<?php

namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Http\Requests\AddCheckItemsRequest;
use App\Http\Requests\OpenCheckRequest;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Services\AuditLogger;
use App\Services\AutoSyncService;
use App\Services\Checks\CheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckController extends Controller
{
    public function store(OpenCheckRequest $request, CheckService $checkService, AuditLogger $auditLogger): RedirectResponse
    {
        $table = DiningTable::findOrFail($request->integer('dining_table_id'));

        $check = $checkService->openCheck($table, $request->user(), $request->validated());

        $auditLogger->record(
            action: 'check.opened',
            subject: $check,
            newValues: [
                'dining_table_id' => $table->id,
                'table_name' => $table->name,
                'guest_count' => $check->guest_count,
            ],
            description: 'Yeni adisyon açıldı.',
            category: 'sales',
        );

        if ($request->boolean('redirect_to_table')) {
            return redirect()->route('tables.show', $table)->with('status', 'Adisyon açıldı.');
        }

        return redirect()->route('tables.show', $table)->with('status', 'Adisyon açıldı.');
    }

    public function addItems(AddCheckItemsRequest $request, Check $check, CheckService $checkService): RedirectResponse
    {
        $checkService->addItems($check, $request->validated('items'));

        // ✅ Çift yönlü senkronizasyon: Kalem eklendiğinde PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        return back()->with('status', 'Kalemler eklendi.');
    }

    public function removeItem(Check $check, CheckItem $item, CheckService $checkService, AuditLogger $auditLogger): RedirectResponse
    {
        if ($item->check_id === $check->id) {
            $removedItem = [
                'check_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
            ];
            $checkService->removeItem($item);

            $auditLogger->record(
                action: 'check.item_removed',
                subject: $check,
                oldValues: $removedItem,
                description: 'Adisyondan ürün kalemi silindi.',
                category: 'sales',
            );
        }

        // ✅ Çift yönlü senkronizasyon: Kalem silindiğinde PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        return back()->with('status', 'Kalem silindi.');
    }

    public function close(Request $request, Check $check, CheckService $checkService, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => 'nullable|string|in:nakit,kredi_karti,yemek_karti',
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        $table = $check->diningTable;
        $paymentMethod = $validated['payment_method'] ?? 'nakit';

        $paidSoFar = $check->payments()->sum('amount');
        $remaining = max(0, $check->total - $paidSoFar);

        $inputAmount = (float) ($validated['amount'] ?? $remaining);
        $amountToPay = min($inputAmount, $remaining);
        if ($amountToPay <= 0 && $remaining > 0) {
            $amountToPay = $remaining;
        }

        DB::transaction(function () use ($check, $paymentMethod, $amountToPay, $checkService) {
            if ($amountToPay > 0) {
                $check->payments()->create([
                    'branch_id' => $check->branch_id,
                    'payment_method' => $paymentMethod,
                    'amount' => $amountToPay,
                    'sync_uuid' => (string) Str::uuid(),
                    'is_synced' => config('database.default') === 'mysql',
                ]);
            }

            $newTotalPaid = $check->payments()->sum('amount');
            if ($newTotalPaid >= ($check->total - 0.01)) {
                $checkService->closeCheck($check, request()->user());
            }
        });

        // ✅ Çift yönlü senkronizasyon: Ödeme alındığında/adisyon kapandığında PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        $newTotalPaid = $check->payments()->sum('amount');
        $isClosed = $check->fresh()->status === CheckStatus::Closed;

        $auditLogger->record(
            action: $isClosed ? 'check.closed' : 'check.partial_payment_received',
            subject: $check,
            newValues: [
                'payment_method' => $paymentMethod,
                'payment_amount' => $amountToPay,
                'total_paid' => $newTotalPaid,
                'status' => $check->fresh()->status,
            ],
            description: $isClosed ? 'Ödeme tamamlandı ve adisyon kapatıldı.' : 'Adisyona kısmi ödeme alındı.',
            category: 'sales',
        );

        if ($isClosed) {
            if ($request->boolean('redirect_to_tables')) {
                return redirect()->route('tables.index')->with('status', 'Ödeme tamamlandı ve adisyon kapatıldı.');
            }
            if ($table) {
                return redirect()->route('tables.show', $table)->with('status', 'Ödeme tamamlandı ve adisyon kapatıldı.');
            }

            return back()->with('status', 'Ödeme tamamlandı ve adisyon kapatıldı.');
        } else {
            $remainingLeft = max(0, $check->total - $newTotalPaid);

            return back()->with('status', 'Kısmi ödeme (₺'.number_format($amountToPay, 2).') alındı. Kalan Bakiye: ₺'.number_format($remainingLeft, 2));
        }
    }

    public function reopen(Check $check, CheckService $checkService, AuditLogger $auditLogger): RedirectResponse
    {
        $oldStatus = $check->status;
        $oldClosedAt = $check->closed_at;
        $checkService->reopenCheck($check, request()->user());

        $auditLogger->record(
            action: 'check.reopened',
            subject: $check,
            oldValues: ['status' => $oldStatus, 'closed_at' => $oldClosedAt],
            newValues: ['status' => $check->fresh()->status, 'closed_at' => $check->fresh()->closed_at],
            description: 'Kapalı adisyon yeniden açıldı.',
            category: 'sales',
        );

        if ($check->diningTable) {
            return redirect()->route('tables.show', $check->diningTable)->with('status', 'Adisyon başarıyla tekrar açıldı.');
        }

        return back()->with('status', 'Adisyon başarıyla tekrar açıldı.');
    }
}
