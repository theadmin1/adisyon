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
        $amount = (float) request('amount', $check->total);

        DB::transaction(function () use ($check, $paymentMethod, $amount, $checkService) {
            if ($amount > 0) {
                $check->payments()->create([
                    'payment_method' => $paymentMethod,
                    'amount' => $amount,
                ]);
            }

            $checkService->closeCheck($check, request()->user());
        });

        if (request()->boolean('redirect_to_tables')) {
            return redirect()->route('tables.index')->with('status', 'Ödeme alındı ve adisyon kapatıldı.');
        }

        if ($table) {
            return redirect()->route('tables.show', $table)->with('status', 'Ödeme alındı ve adisyon kapatıldı.');
        }

        return redirect()->route('tables.index')->with('status', 'Ödeme alındı ve adisyon kapatıldı.');
    }
}
