<?php

namespace App\Http\Controllers;

use App\Http\Requests\MergeChecksRequest;
use App\Http\Requests\MoveCheckRequest;
use App\Http\Requests\SplitCheckRequest;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Models\Product;
use App\Services\Checks\CheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckActionController extends Controller
{
    public function treat(Request $request, Check $check, CheckService $checkService)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        $product = Product::find($request->product_id);

        DB::transaction(function () use ($request, $check, $product, $checkService) {
            $check->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $request->quantity,
                'unit_price' => $product->price,
                'total_price' => $product->price * $request->quantity,
                'is_complimentary' => true,
                'complimentary_reason' => $request->reason ?? 'İkram',
            ]);

            $checkService->recalculateTotals($check);
        });

        return back()->with('status', 'Yeni ürün ikram olarak eklendi.');
    }

    public function void(Request $request, Check $check, CheckService $checkService)
    {
        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:check_items,id',
        ]);

        DB::transaction(function () use ($request, $check, $checkService) {
            foreach ($request->item_ids as $itemId) {
                $item = CheckItem::where('check_id', $check->id)->find($itemId);
                if ($item && !$item->is_cancelled) {
                    $item->update([
                        'is_cancelled' => true,
                        'cancelled_at' => now(),
                    ]);

                    if ($item->product_id) {
                        \App\Models\StockMovement::create([
                            'product_id' => $item->product_id,
                            'check_id' => $check->id,
                            'check_item_id' => $item->id,
                            'type' => 'cancellation_pending',
                            'quantity' => $item->quantity,
                            'status' => 'pending_approval',
                            'notes' => "Masa #" . ($check->diningTable?->name ?? 'Tezgah') . " sipariş iptali (Stoka iade onayı bekliyor)",
                        ]);
                    }
                }
            }
            $checkService->recalculateTotals($check);
        });

        return back()->with('status', 'Seçili kalemler iade / iptal edildi.');
    }

    public function discount(Request $request, Check $check, CheckService $checkService)
    {
        $request->validate([
            'type' => 'required|in:amount,percentage',
            'value' => 'required|numeric|min:0',
        ]);

        $subtotal = $check->items()->where('is_cancelled', false)->where('is_complimentary', false)->sum('total_price');

        $discountAmount = 0;
        if ($request->type === 'percentage') {
            $discountAmount = $subtotal * ($request->value / 100);
        } else {
            $discountAmount = $request->value;
        }

        if ($discountAmount > $subtotal) {
            $discountAmount = $subtotal;
        }

        $check->update([
            'discount_total' => $discountAmount,
        ]);

        $checkService->recalculateTotals($check);

        return back()->with('status', 'İskonto başarıyla uygulandı.');
    }

    public function split(SplitCheckRequest $request, Check $check, CheckService $checkService)
    {
        try {
            $splitCheck = $checkService->splitCheck($check, $request->validated('item_ids'), $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['split' => $exception->getMessage()]);
        }

        return redirect()
            ->route('tables.show', $check->dining_table_id)
            ->with('status', 'Adisyon bölündü: '.$splitCheck->check_number);
    }

    public function merge(MergeChecksRequest $request, Check $check, CheckService $checkService)
    {
        $sourceIds = Check::query()
            ->whereIn('id', $request->validated('source_check_ids'))
            ->pluck('id')
            ->all();

        try {
            $checkService->mergeChecks($check, $sourceIds, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['merge' => $exception->getMessage()]);
        }

        return back()->with('status', 'Adisyonlar birleştirildi.');
    }

    public function move(MoveCheckRequest $request, Check $check, CheckService $checkService)
    {
        $targetTable = DiningTable::findOrFail($request->integer('dining_table_id'));

        if ($targetTable->id === $check->dining_table_id) {
            return back()->withErrors(['move' => 'Adisyon zaten bu masada.']);
        }

        $checkService->moveCheck($check, $targetTable, $request->user());

        return redirect()
            ->route('tables.show', $targetTable)
            ->with('status', 'Adisyon '.$targetTable->name.' masasına taşındı.');
    }
}
