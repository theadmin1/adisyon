<?php

namespace App\Http\Controllers;

use App\Http\Requests\MergeChecksRequest;
use App\Http\Requests\MoveCheckRequest;
use App\Http\Requests\SplitCheckRequest;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\AutoSyncService;
use App\Services\Checks\CheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CheckActionController extends Controller
{
    public function treat(Request $request, Check $check, CheckService $checkService, AuditLogger $auditLogger)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $check, $checkService) {
            $product = Product::where('branch_id', $check->branch_id)
                ->whereKey($request->product_id)
                ->lockForUpdate()
                ->firstOrFail();
            $quantity = (float) $request->quantity;

            if ($product->track_stock && (float) $product->stock_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "{$product->name} için yeterli stok yok.",
                ]);
            }

            $item = $check->items()->create([
                'branch_id' => $check->branch_id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'total_price' => $product->price * $quantity,
                'is_complimentary' => true,
                'complimentary_reason' => $request->reason ?? 'İkram',
                'sync_uuid' => (string) Str::uuid(),
                'is_synced' => config('database.default') === 'mysql',
            ]);

            if ($product->track_stock) {
                $product->decrement('stock_quantity', $quantity);
            }

            StockMovement::create([
                'branch_id' => $check->branch_id,
                'sync_uuid' => (string) Str::uuid(),
                'is_synced' => config('database.default') === 'mysql',
                'product_id' => $product->id,
                'check_id' => $check->id,
                'check_item_id' => $item->id,
                'type' => 'sale_deduction',
                'quantity' => $quantity,
                'status' => 'completed',
                'notes' => "İkram stok düşümü (#{$check->check_number})",
            ]);

            $checkService->recalculateTotals($check);
        });

        // ✅ Çift yönlü senkronizasyon: İkram eklendiğinde PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        $auditLogger->record(
            action: 'check.complimentary_item_added',
            subject: $check,
            newValues: [
                'product_id' => $request->integer('product_id'),
                'quantity' => $request->integer('quantity'),
                'reason' => $request->input('reason') ?: 'İkram',
            ],
            description: 'Adisyona ikram ürün eklendi.',
            category: 'sales',
        );

        return back()->with('status', 'Yeni ürün ikram olarak eklendi.');
    }

    public function void(Request $request, Check $check, CheckService $checkService, AuditLogger $auditLogger)
    {
        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:check_items,id',
        ]);

        DB::transaction(function () use ($request, $check, $checkService) {
            foreach ($request->item_ids as $itemId) {
                $item = CheckItem::where('check_id', $check->id)->lockForUpdate()->find($itemId);
                if ($item && ! $item->is_cancelled) {
                    $item->update([
                        'is_cancelled' => true,
                        'cancelled_at' => now(),
                        'is_synced' => config('database.default') === 'mysql',
                    ]);

                    if ($item->product_id) {
                        StockMovement::create([
                            'branch_id' => $check->branch_id,
                            'sync_uuid' => (string) Str::uuid(),
                            'is_synced' => config('database.default') === 'mysql',
                            'product_id' => $item->product_id,
                            'check_id' => $check->id,
                            'check_item_id' => $item->id,
                            'type' => 'cancellation_pending',
                            'quantity' => $item->quantity,
                            'status' => 'pending_approval',
                            'notes' => 'Masa #'.($check->diningTable?->name ?? 'Tezgah').' sipariş iptali (Stoka iade onayı bekliyor)',
                        ]);
                    }
                }
            }
            $checkService->recalculateTotals($check);
        });

        // ✅ Çift yönlü senkronizasyon: İade/İptal yapıldığında PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        $auditLogger->record(
            action: 'check.items_cancelled',
            subject: $check,
            newValues: ['item_ids' => $request->input('item_ids')],
            description: 'Adisyon kalemleri iptal/iade edildi.',
            category: 'sales',
        );

        return back()->with('status', 'Seçili kalemler iade / iptal edildi.');
    }

    public function discount(Request $request, Check $check, CheckService $checkService, AuditLogger $auditLogger)
    {
        $request->validate([
            'type' => 'required|in:amount,percentage',
            'value' => 'required|numeric|min:0',
        ]);

        $oldDiscount = $check->discount_total;
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

        // ✅ Çift yönlü senkronizasyon: İskonto uygulandığında PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        $auditLogger->record(
            action: 'check.discount_applied',
            subject: $check,
            oldValues: ['discount_total' => $oldDiscount],
            newValues: [
                'discount_total' => $discountAmount,
                'discount_type' => $request->input('type'),
                'discount_value' => $request->input('value'),
            ],
            description: 'Adisyona iskonto uygulandı.',
            category: 'sales',
        );

        return back()->with('status', 'İskonto başarıyla uygulandı.');
    }

    public function split(SplitCheckRequest $request, Check $check, CheckService $checkService, AuditLogger $auditLogger)
    {
        try {
            $splitCheck = $checkService->splitCheck($check, $request->validated('item_ids'), $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['split' => $exception->getMessage()]);
        }

        $auditLogger->record(
            action: 'check.split',
            subject: $check,
            newValues: [
                'item_ids' => $request->validated('item_ids'),
                'new_check_id' => $splitCheck->id,
                'new_check_number' => $splitCheck->check_number,
            ],
            description: 'Adisyon bölündü.',
            category: 'sales',
        );

        return redirect()
            ->route('tables.show', $check->dining_table_id)
            ->with('status', 'Adisyon bölündü: '.$splitCheck->check_number);
    }

    public function merge(MergeChecksRequest $request, Check $check, CheckService $checkService, AuditLogger $auditLogger)
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

        $auditLogger->record(
            action: 'check.merged',
            subject: $check,
            newValues: ['source_check_ids' => $sourceIds],
            description: 'Adisyonlar birleştirildi.',
            category: 'sales',
        );

        return back()->with('status', 'Adisyonlar birleştirildi.');
    }

    public function move(MoveCheckRequest $request, Check $check, CheckService $checkService, AuditLogger $auditLogger)
    {
        $targetTable = DiningTable::findOrFail($request->integer('dining_table_id'));
        $oldTableId = $check->dining_table_id;
        $oldTableName = $check->diningTable?->name;

        if ($targetTable->id === $check->dining_table_id) {
            return back()->withErrors(['move' => 'Adisyon zaten bu masada.']);
        }

        $checkService->moveCheck($check, $targetTable, $request->user());

        $auditLogger->record(
            action: 'check.moved',
            subject: $check,
            oldValues: ['dining_table_id' => $oldTableId, 'table_name' => $oldTableName],
            newValues: ['dining_table_id' => $targetTable->id, 'table_name' => $targetTable->name],
            description: 'Adisyon başka masaya taşındı.',
            category: 'sales',
        );

        return redirect()
            ->route('tables.show', $targetTable)
            ->with('status', 'Adisyon '.$targetTable->name.' masasına taşındı.');
    }
}
