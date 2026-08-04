<?php

namespace App\Observers;

use App\Events\TableStatusUpdated;
use App\Events\WaiterRealtimeUpdated;
use App\Models\Category;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Payment;
use App\Models\Product;
use App\Services\TableLockService;
use App\Support\CatalogVersion;
use Illuminate\Database\Eloquent\Model;

class WaiterRealtimeObserver
{
    /** @return array<int, class-string<Model>> */
    public static function observedModels(): array
    {
        return [
            Hall::class,
            DiningTable::class,
            Category::class,
            Product::class,
            Check::class,
            CheckItem::class,
            Payment::class,
        ];
    }

    public function saved(Model $model): void
    {
        $this->touchCatalog($model);
        $this->broadcast($model, $model->wasRecentlyCreated ? 'created' : 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->touchCatalog($model);
        $this->broadcast($model, 'deleted');
    }

    private function touchCatalog(Model $model): void
    {
        if ($model instanceof Category || $model instanceof Product) {
            CatalogVersion::touch((int) $model->getAttribute('branch_id'));
        }
    }

    private function broadcast(Model $model, string $operation): void
    {
        $branchId = (int) $model->getAttribute('branch_id');
        if ($branchId <= 0) {
            return;
        }

        WaiterRealtimeUpdated::dispatch(
            $branchId,
            $this->topics($model),
            class_basename($model).'.'.$operation,
            $this->references($model),
        );

        if ($model instanceof DiningTable && $operation !== 'deleted') {
            $lockState = app(TableLockService::class)->stateForTable($model);

            TableStatusUpdated::dispatch(
                $branchId,
                (int) $model->getKey(),
                $model->status?->value ?? (string) $model->status,
                (bool) $lockState['is_locked'],
                $lockState['locked_by'],
            );
        }
    }

    /** @return array<int, string> */
    private function topics(Model $model): array
    {
        return match (true) {
            $model instanceof Hall, $model instanceof DiningTable => ['tables'],
            $model instanceof Category, $model instanceof Product => ['menu'],
            $model instanceof CheckItem => ['orders', 'kitchen'],
            $model instanceof Payment => ['orders', 'payments', 'tables'],
            $model instanceof Check => $model->wasChanged('kitchen_sent_at')
                ? ['orders', 'tables', 'kitchen']
                : ['orders', 'tables'],
            default => ['sync'],
        };
    }

    /** @return array<string, int|null> */
    private function references(Model $model): array
    {
        return [
            'hall_id' => $model instanceof Hall ? (int) $model->getKey() : $this->integer($model, 'hall_id'),
            'table_id' => $model instanceof DiningTable ? (int) $model->getKey() : $this->integer($model, 'dining_table_id'),
            'order_id' => $model instanceof Check ? (int) $model->getKey() : $this->integer($model, 'check_id'),
            'item_id' => $model instanceof CheckItem ? (int) $model->getKey() : null,
            'payment_id' => $model instanceof Payment ? (int) $model->getKey() : null,
            'category_id' => $model instanceof Category ? (int) $model->getKey() : $this->integer($model, 'category_id'),
            'product_id' => $model instanceof Product ? (int) $model->getKey() : $this->integer($model, 'product_id'),
        ];
    }

    private function integer(Model $model, string $attribute): ?int
    {
        $value = $model->getAttribute($attribute);

        return is_numeric($value) ? (int) $value : null;
    }
}
