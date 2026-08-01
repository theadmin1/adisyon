<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionRecipe;
use App\Models\ProductionWorkflow;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductionWorkflowService
{
    private const UNITS = [
        'g' => ['group' => 'weight', 'factor' => 1],
        'kg' => ['group' => 'weight', 'factor' => 1000],
        'ml' => ['group' => 'volume', 'factor' => 1],
        'l' => ['group' => 'volume', 'factor' => 1000],
        'adet' => ['group' => 'count', 'factor' => 1],
        'porsiyon' => ['group' => 'count', 'factor' => 1],
    ];

    public static function allowedUnits(): array
    {
        return array_keys(self::UNITS);
    }

    public function normalizeUnit(string $unit): string
    {
        $unit = mb_strtolower(trim($unit), 'UTF-8');
        return match ($unit) {
            'gr', 'gram' => 'g',
            'kilogram' => 'kg',
            'lt', 'litre', 'liter' => 'l',
            'mililitre', 'mililiter' => 'ml',
            'tane' => 'adet',
            default => $unit,
        };
    }

    public function convert(float $quantity, string $fromUnit, string $toUnit): float
    {
        $from = $this->normalizeUnit($fromUnit);
        $to = $this->normalizeUnit($toUnit);
        if ($from === $to) return round($quantity, 4);
        if (! isset(self::UNITS[$from], self::UNITS[$to]) || self::UNITS[$from]['group'] !== self::UNITS[$to]['group']) {
            throw ValidationException::withMessages(['unit' => "{$fromUnit} birimi {$toUnit} birimine dönüştürülemiyor."]);
        }
        return round($quantity * self::UNITS[$from]['factor'] / self::UNITS[$to]['factor'], 4);
    }

    public function requirements(ProductionRecipe $recipe, float $servings): Collection
    {
        $recipe->loadMissing('items.ingredient');
        $factor = $servings / max(0.001, (float) $recipe->base_servings);

        return $recipe->items->map(function ($item) use ($factor) {
            $ingredient = $item->ingredient;
            $required = $this->convert((float) $item->quantity * $factor, $item->unit, $ingredient->unit);
            return (object) [
                'product' => $ingredient,
                'recipe_quantity' => (float) $item->quantity,
                'recipe_unit' => $item->unit,
                'required' => $required,
                'available' => (float) $ingredient->stock_quantity,
                'unit' => $ingredient->unit,
                'sufficient' => (float) $ingredient->stock_quantity + 0.00001 >= $required,
            ];
        });
    }

    public function create(ProductionRecipe $recipe, float $servings, User $user, ?string $scheduledFor = null, ?string $notes = null): ProductionWorkflow
    {
        return ProductionWorkflow::create([
            'branch_id' => $recipe->branch_id,
            'production_recipe_id' => $recipe->id,
            'created_by_user_id' => $user->id,
            'workflow_number' => 'IA-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'recipe_name' => $recipe->name,
            'planned_servings' => $servings,
            'status' => 'planned',
            'scheduled_for' => $scheduledFor,
            'notes' => $notes,
        ]);
    }

    public function start(ProductionWorkflow $workflow): void
    {
        DB::transaction(function () use ($workflow): void {
            $locked = ProductionWorkflow::withoutGlobalScopes()->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'planned') throw ValidationException::withMessages(['workflow' => 'Yalnızca planlanan iş akışı başlatılabilir.']);
            $locked->update(['status' => 'in_progress', 'started_at' => now()]);
        });
    }

    public function complete(ProductionWorkflow $workflow, User $user): void
    {
        DB::transaction(function () use ($workflow, $user): void {
            $locked = ProductionWorkflow::withoutGlobalScopes()->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['planned', 'in_progress'], true)) {
                throw ValidationException::withMessages(['workflow' => 'Bu iş akışı daha önce tamamlanmış veya iptal edilmiş.']);
            }

            $recipe = ProductionRecipe::withoutGlobalScopes()->with('items')->whereKey($locked->production_recipe_id)->first();
            if (! $recipe) throw ValidationException::withMessages(['workflow' => 'İş akışının reçetesi bulunamadı.']);

            $ingredientIds = $recipe->items->pluck('ingredient_product_id')->sort()->values();
            $products = Product::withoutGlobalScopes()->whereIn('id', $ingredientIds)->lockForUpdate()->get()->keyBy('id');
            $factor = (float) $locked->planned_servings / max(0.001, (float) $recipe->base_servings);
            $deductions = collect();

            foreach ($recipe->items as $item) {
                $product = $products->get($item->ingredient_product_id);
                if (! $product || (int) $product->branch_id !== (int) $locked->branch_id) {
                    throw ValidationException::withMessages(['workflow' => 'Reçetedeki bir hammadde bu şubede bulunamadı.']);
                }
                $required = $this->convert((float) $item->quantity * $factor, $item->unit, $product->unit);
                if ((float) $product->stock_quantity + 0.00001 < $required) {
                    throw ValidationException::withMessages(['stock' => "{$product->name} için yetersiz stok. Gereken: {$required} {$product->unit}, mevcut: {$product->stock_quantity} {$product->unit}."]);
                }
                $deductions->push([$item, $product, $required]);
            }

            foreach ($deductions as [$item, $product, $required]) {
                $before = (float) $product->stock_quantity;
                $product->decrement('stock_quantity', $required);
                $after = (float) $product->fresh()->stock_quantity;
                $locked->items()->create([
                    'product_id' => $product->id, 'product_name' => $product->name, 'stock_unit' => $product->unit,
                    'recipe_quantity' => $item->quantity, 'recipe_unit' => $item->unit, 'required_quantity' => $required,
                    'consumed_quantity' => $required, 'stock_before' => $before, 'stock_after' => $after,
                ]);
                StockMovement::withoutGlobalScopes()->create([
                    'branch_id' => $locked->branch_id, 'product_id' => $product->id, 'production_workflow_id' => $locked->id,
                    'sync_uuid' => (string) Str::uuid(), 'is_synced' => true, 'type' => 'workflow_consumption',
                    'quantity' => $required, 'status' => 'completed', 'approved_by_user_id' => $user->id, 'approved_at' => now(),
                    'notes' => "{$locked->workflow_number} üretim tüketimi ({$locked->planned_servings} porsiyon {$locked->recipe_name})",
                ]);
            }

            $output = Product::withoutGlobalScopes()->whereKey($recipe->output_product_id)->lockForUpdate()->first();
            if ($output && $output->track_stock && (int) $output->branch_id === (int) $locked->branch_id) {
                $output->increment('stock_quantity', (float) $locked->planned_servings);
                StockMovement::withoutGlobalScopes()->create([
                    'branch_id' => $locked->branch_id, 'product_id' => $output->id, 'production_workflow_id' => $locked->id,
                    'sync_uuid' => (string) Str::uuid(), 'is_synced' => true, 'type' => 'workflow_output',
                    'quantity' => $locked->planned_servings, 'status' => 'completed', 'approved_by_user_id' => $user->id, 'approved_at' => now(),
                    'notes' => "{$locked->workflow_number} üretim çıktısı",
                ]);
            }

            $locked->update(['status' => 'completed', 'started_at' => $locked->started_at ?? now(), 'completed_at' => now(), 'completed_by_user_id' => $user->id]);
        });
    }

    public function cancel(ProductionWorkflow $workflow): void
    {
        DB::transaction(function () use ($workflow): void {
            $locked = ProductionWorkflow::withoutGlobalScopes()->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['planned', 'in_progress'], true)) throw ValidationException::withMessages(['workflow' => 'Tamamlanan veya iptal edilen iş akışı değiştirilemez.']);
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });
    }
}
