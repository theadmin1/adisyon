<?php

namespace App\Services\Checks;

use App\Enums\CheckStatus;
use App\Enums\TableStatus;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AutoSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CheckService
{
    public function openCheck(DiningTable $table, ?User $waiter = null, array $attributes = []): Check
    {
        $check = DB::transaction(function () use ($table, $waiter, $attributes) {
            $isSynced = config('database.default') === 'mysql';
            $check = Check::create([
                'branch_id' => $table->branch_id,
                'dining_table_id' => $table->id,
                'waiter_id' => $waiter?->id,
                'check_number' => 'CHK-'.Str::upper(Str::random(8)),
                'sync_uuid' => (string) Str::uuid(),
                'is_synced' => $isSynced,
                'guest_count' => $attributes['guest_count'] ?? 1,
                'status' => CheckStatus::Open,
                'opened_at' => now(),
            ]);

            $table->update([
                'status' => TableStatus::Occupied,
                'occupant_count' => $attributes['guest_count'] ?? 1,
            ]);

            return $check;
        });

        // ✅ Çift yönlü senkronizasyon: Masa açıldığında arka planda PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        return $check;
    }

    public function addItems(Check $check, array $items): Check
    {
        return DB::transaction(function () use ($check, $items) {
            $isSynced = config('database.default') === 'mysql';

            foreach ($items as $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                if ($quantity <= 0) {
                    throw new RuntimeException('Ürün miktarı sıfırdan büyük olmalıdır.');
                }

                $product = null;
                if (isset($item['product_id'])) {
                    $product = Product::query()
                        ->where('branch_id', $check->branch_id)
                        ->whereKey($item['product_id'])
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $unitPrice = (float) ($item['unit_price'] ?? (($product?->discounted_price ?: $product?->price) ?? 0));
                $notes = $item['notes'] ?? null;

                if ($product?->track_stock && (float) $product->stock_quantity < $quantity) {
                    throw new RuntimeException("{$product->name} için yeterli stok yok.");
                }

                $existingItem = $check->items()
                    ->where('is_cancelled', false)
                    ->where('is_complimentary', false)
                    ->where('product_id', $product?->id)
                    ->where('notes', $notes)
                    ->lockForUpdate()
                    ->first();

                if ($existingItem) {
                    $newQuantity = (float) $existingItem->quantity + $quantity;
                    $existingItem->update([
                        'quantity' => $newQuantity,
                        'total_price' => (float) $existingItem->unit_price * $newQuantity,
                        'is_synced' => $isSynced,
                    ]);
                } else {
                    $check->items()->create([
                        'branch_id' => $check->branch_id,
                        'product_id' => $product?->id,
                        'product_name' => $item['product_name'] ?? $product?->name ?? 'Ürün',
                        'sync_uuid' => (string) Str::uuid(),
                        'is_synced' => $isSynced,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $unitPrice * $quantity,
                        'notes' => $notes,
                    ]);
                }

                if ($product) {
                    if ($product->track_stock) {
                        $product->decrement('stock_quantity', $quantity);
                        $product->update(['is_synced' => $isSynced]);
                    }

                    StockMovement::create([
                        'branch_id' => $check->branch_id,
                        'sync_uuid' => (string) Str::uuid(),
                        'is_synced' => $isSynced,
                        'product_id' => $product->id,
                        'check_id' => $check->id,
                        'type' => 'sale_deduction',
                        'quantity' => $quantity,
                        'status' => 'completed',
                        'notes' => 'Masa #'.($check->diningTable?->name ?? 'Tezgah').' adisyon satışı',
                    ]);
                }
            }

            return $this->recalculateTotals($check->fresh('items'));
        });
    }

    public function removeItem(CheckItem $item): Check
    {
        return DB::transaction(function () use ($item) {
            $item = CheckItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $check = $item->check;

            if ($item->is_cancelled) {
                return $check;
            }

            if ($item->product_id) {
                $product = Product::query()
                    ->where('branch_id', $check->branch_id)
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($product?->track_stock) {
                    $product->increment('stock_quantity', (float) $item->quantity);
                }

                StockMovement::create([
                    'branch_id' => $check->branch_id,
                    'sync_uuid' => (string) Str::uuid(),
                    'is_synced' => config('database.default') === 'mysql',
                    'product_id' => $item->product_id,
                    'check_id' => $check->id,
                    'check_item_id' => $item->id,
                    'type' => 'return_approved',
                    'quantity' => (float) $item->quantity,
                    'status' => 'completed',
                    'notes' => "Adisyon kalemi iptal edildi (#{$check->check_number})",
                ]);
            }

            if (config('database.default') === 'mysql') {
                $item->delete();
            } else {
                $item->update([
                    'is_cancelled' => true,
                    'cancelled_at' => now(),
                    'is_synced' => false,
                ]);
            }

            return $this->recalculateTotals($check->fresh('items'));
        });
    }

    public function moveCheck(Check $check, DiningTable $targetTable, ?User $actor = null): Check
    {
        $result = DB::transaction(function () use ($check, $targetTable) {
            $oldTable = $check->diningTable;

            $check->update([
                'dining_table_id' => $targetTable->id,
                'is_synced' => config('database.default') === 'mysql',
            ]);

            $targetTable->update([
                'status' => TableStatus::Occupied,
                'occupant_count' => $oldTable?->occupant_count ?: $check->guest_count,
            ]);

            if ($oldTable && $oldTable->id !== $targetTable->id && ! $this->hasOpenChecks($oldTable, $check->id)) {
                $oldTable->update([
                    'status' => TableStatus::Available,
                    'occupant_count' => 0,
                ]);
            }

            return $check->fresh('diningTable');
        });

        // ✅ Çift yönlü senkronizasyon: Masa taşındığında arka planda PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        return $result;
    }

    public function splitCheck(Check $check, array $itemIds, ?User $actor = null): Check
    {
        $splitCheck = DB::transaction(function () use ($check, $itemIds, $actor) {
            $items = $check->items()
                ->whereIn('id', $itemIds)
                ->where('is_cancelled', false)
                ->get();

            if ($items->isEmpty()) {
                throw new RuntimeException('Bölünecek kalem seçilmedi.');
            }

            if ($items->count() >= $check->items()->where('is_cancelled', false)->count()) {
                throw new RuntimeException('Adisyondaki tüm kalemler seçilemez; en az bir kalem kalmalıdır.');
            }

            $isSynced = config('database.default') === 'mysql';

            $splitCheck = Check::create([
                'branch_id' => $check->branch_id,
                'dining_table_id' => $check->dining_table_id,
                'waiter_id' => $actor?->id ?? $check->waiter_id,
                'check_number' => 'SPL-'.Str::upper(Str::random(8)),
                'sync_uuid' => (string) Str::uuid(),
                'is_synced' => $isSynced,
                'guest_count' => 1,
                'status' => CheckStatus::Open,
                'opened_at' => now(),
            ]);

            CheckItem::query()
                ->whereIn('id', $items->pluck('id'))
                ->update(['check_id' => $splitCheck->id, 'is_synced' => $isSynced]);

            $this->recalculateTotals($check->fresh('items'));

            return $this->recalculateTotals($splitCheck->fresh('items'));
        });

        // ✅ Çift yönlü senkronizasyon: Adisyon bölündüğünde arka planda PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        return $splitCheck;
    }

    public function mergeChecks(Check $target, array $sourceCheckIds, ?User $actor = null): Check
    {
        $result = DB::transaction(function () use ($target, $sourceCheckIds) {
            $isSynced = config('database.default') === 'mysql';

            $sources = Check::query()
                ->whereIn('id', $sourceCheckIds)
                ->whereKeyNot($target->id)
                ->whereIn('status', [CheckStatus::Open, CheckStatus::AwaitingPayment])
                ->with('diningTable')
                ->get();

            if ($sources->isEmpty()) {
                throw new RuntimeException('Birleştirilebilecek açık adisyon bulunamadı.');
            }

            $guestCount = $target->guest_count;

            foreach ($sources as $source) {
                $source->items()->update(['check_id' => $target->id, 'is_synced' => $isSynced]);
                $source->payments()->update(['check_id' => $target->id]);

                $guestCount += $source->guest_count;

                $source->update([
                    'status' => CheckStatus::Closed,
                    'closed_at' => now(),
                    'subtotal' => 0,
                    'discount_total' => 0,
                    'total' => 0,
                    'is_synced' => $isSynced,
                ]);

                $sourceTable = $source->diningTable;

                if ($sourceTable && $sourceTable->id !== $target->dining_table_id && ! $this->hasOpenChecks($sourceTable)) {
                    $sourceTable->update([
                        'status' => TableStatus::Available,
                        'occupant_count' => 0,
                    ]);
                }
            }

            $target->update(['guest_count' => $guestCount, 'is_synced' => $isSynced]);

            return $this->recalculateTotals($target->fresh('items'));
        });

        // ✅ Çift yönlü senkronizasyon: Adisyonlar birleştirildiğinde arka planda PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        return $result;
    }

    protected function hasOpenChecks(DiningTable $table, ?int $excludingCheckId = null): bool
    {
        return $table->checks()
            ->whereIn('status', [CheckStatus::Open, CheckStatus::AwaitingPayment])
            ->when($excludingCheckId, fn ($query) => $query->whereKeyNot($excludingCheckId))
            ->exists();
    }

    public function reopenCheck(Check $check, ?User $actor = null): Check
    {
        $isSynced = config('database.default') === 'mysql';

        DB::transaction(function () use ($check, $isSynced) {
            $check->update([
                'status' => CheckStatus::Open,
                'closed_at' => null,
                'is_synced' => $isSynced,
            ]);

            if ($check->diningTable) {
                $check->diningTable->update([
                    'status' => TableStatus::Occupied,
                ]);
            }
        });

        // ✅ Çift yönlü senkronizasyon: Adisyon tekrar açıldığında arka planda PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        return $check->fresh();
    }

    public function closeCheck(Check $check, ?User $cashier = null): Check
    {
        $check->update([
            'status' => CheckStatus::Closed,
            'closed_at' => now(),
            'is_synced' => config('database.default') === 'mysql',
        ]);

        if ($check->diningTable && ! $this->hasOpenChecks($check->diningTable, $check->id)) {
            $check->diningTable->update([
                'status' => TableStatus::Available,
                'occupant_count' => 0,
            ]);
        }

        // ✅ Çift yönlü senkronizasyon: Adisyon kapandığında arka planda PUSH/PULL tetikle
        AutoSyncService::syncIfLocal();

        return $check->fresh();
    }

    public function recalculateTotals(Check $check): Check
    {
        $subtotal = $check->items()
            ->where('is_cancelled', false)
            ->where('is_complimentary', false)
            ->sum('total_price');

        $discountTotal = (float) $check->discount_total;
        $total = max(0, $subtotal - $discountTotal);

        $check->update([
            'subtotal' => $subtotal,
            'total' => $total,
            'is_synced' => config('database.default') === 'mysql',
        ]);

        AutoSyncService::syncIfLocal();

        return $check->fresh();
    }
}
