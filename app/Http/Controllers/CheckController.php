<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCheckItemsRequest;
use App\Http\Requests\OpenCheckRequest;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Services\Checks\CheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return back()->with('status', 'Kalemler eklendi.');
    }

    public function removeItem(Check $check, CheckItem $item, CheckService $checkService): RedirectResponse
    {
        if ($item->check_id === $check->id) {
            $checkService->removeItem($item);
        }

        return back()->with('status', 'Kalem silindi.');
    }

    public function close(Check $check, CheckService $checkService): RedirectResponse
    {
        $table = $check->diningTable;
        $paymentMethod = request('payment_method', 'nakit');

        $paidSoFar = $check->payments()->sum('amount');
        $remaining = max(0, $check->total - $paidSoFar);

        $inputAmount = (float) request('amount', $remaining);
        $amountToPay = min($inputAmount, $remaining);
        if ($amountToPay <= 0 && $remaining > 0) {
            $amountToPay = $remaining;
        }

        DB::transaction(function () use ($check, $paymentMethod, $amountToPay, $checkService) {
            if ($amountToPay > 0) {
                $check->payments()->create([
                    'payment_method' => $paymentMethod,
                    'amount' => $amountToPay,
                ]);
            }

            $newTotalPaid = $check->payments()->sum('amount');
            if ($newTotalPaid >= ($check->total - 0.01) || request()->boolean('close_anyway')) {
                $checkService->closeCheck($check, request()->user());
            }
        });

        $newTotalPaid = $check->payments()->sum('amount');
        $isClosed = $check->fresh()->status === 'closed';

        if ($isClosed) {
            if (request()->boolean('redirect_to_tables')) {
                return redirect()->route('tables.index')->with('status', 'Ödeme tamamlandı ve adisyon kapatıldı.');
            }
            if ($table) {
                return redirect()->route('tables.show', $table)->with('status', 'Ödeme tamamlandı ve adisyon kapatıldı.');
            }
            return back()->with('status', 'Ödeme tamamlandı ve adisyon kapatıldı.');
        } else {
            $remainingLeft = max(0, $check->total - $newTotalPaid);
            return back()->with('status', 'Kısmi ödeme (₺' . number_format($amountToPay, 2) . ') alındı. Kalan Bakiye: ₺' . number_format($remainingLeft, 2));
        }
    }
}
