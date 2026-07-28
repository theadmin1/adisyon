<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCheckItemsRequest;
use App\Http\Requests\OpenCheckRequest;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Services\AutoSyncService;
use App\Services\Checks\CheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckController extends Controller
{
    public function store(OpenCheckRequest $request, CheckService $checkService): RedirectResponse
    {
        $table = DiningTable::findOrFail($request->integer('dining_table_id'));

        $check = $checkService->openCheck($table, $request->user(), $request->validated());

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

    public function removeItem(Check $check, CheckItem $item, CheckService $checkService): RedirectResponse
    {
        if ($item->check_id === $check->id) {
            $checkService->removeItem($item);
        }

        // ✅ Çift yönlü senkronizasyon: Kalem silindiğinde PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        return back()->with('status', 'Kalem silindi.');
    }

    public function close(Request $request, Check $check, CheckService $checkService): RedirectResponse
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
        $isClosed = $check->fresh()->status === 'closed';

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

    public function reopen(Check $check, CheckService $checkService): RedirectResponse
    {
        $checkService->reopenCheck($check, request()->user());

        if ($check->diningTable) {
            return redirect()->route('tables.show', $check->diningTable)->with('status', 'Adisyon başarıyla tekrar açıldı.');
        }

        return back()->with('status', 'Adisyon başarıyla tekrar açıldı.');
    }
}
