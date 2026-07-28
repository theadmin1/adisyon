<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\Category;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DeliveryIntegration;
use App\Models\DeliveryOrder;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\LoginLog;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierProductSubmission;
use App\Models\SupplierProductSubmissionItem;
use App\Services\AutoSyncService;
use App\Services\OfflineSyncRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OfflineSyncObserver
{
    /**
     * @return array<int, class-string<Model>>
     */
    public static function observedModels(): array
    {
        return [
            StaffProfile::class,
            Hall::class,
            DiningTable::class,
            Category::class,
            Product::class,
            Check::class,
            CheckItem::class,
            Payment::class,
            StockMovement::class,
            Setting::class,
            DeliveryIntegration::class,
            DeliveryOrder::class,
            Printer::class,
            CashShift::class,
            CashMovement::class,
            Supplier::class,
            PurchaseOrder::class,
            PurchaseOrderItem::class,
            PurchaseReceipt::class,
            PurchaseReceiptItem::class,
            SupplierProductSubmission::class,
            SupplierProductSubmissionItem::class,
            LoginLog::class,
            AuditLog::class,
        ];
    }

    public function creating(Model $model): void
    {
        if (! $this->supports($model)) {
            return;
        }

        if (! $model->getAttribute('sync_uuid')) {
            $model->setAttribute('sync_uuid', (string) Str::uuid());
        }

        $model->setAttribute('is_synced', config('database.default') !== 'sqlite');
    }

    public function updating(Model $model): void
    {
        if (! $this->supports($model) || config('database.default') !== 'sqlite') {
            return;
        }

        $businessChanges = array_diff(
            array_keys($model->getDirty()),
            ['sync_uuid', 'is_synced', 'synced_at', 'created_at', 'updated_at']
        );

        if ($businessChanges !== []) {
            $model->setAttribute('is_synced', false);
        }
    }

    public function deleting(Model $model): void
    {
        if (! $this->supports($model) || config('database.default') !== 'sqlite') {
            return;
        }

        $syncUuid = $model->getAttribute('sync_uuid') ?: (string) Str::uuid();
        if (! $model->getAttribute('sync_uuid')) {
            DB::connection($model->getConnectionName())
                ->table($model->getTable())
                ->where('id', $model->getKey())
                ->update(['sync_uuid' => $syncUuid]);
        }

        if (Schema::connection($model->getConnectionName())->hasTable('deleted_records')) {
            DB::connection($model->getConnectionName())->table('deleted_records')->updateOrInsert(
                ['sync_uuid' => $syncUuid, 'type' => $model->getTable()],
                [
                    'record_id' => $model->getKey(),
                    'name' => $this->displayName($model),
                    'is_synced' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function saved(Model $model): void
    {
        if (! app()->runningUnitTests()
            && $this->supports($model)
            && config('database.default') === 'sqlite') {
            AutoSyncService::syncIfLocal();
        }
    }

    public function deleted(Model $model): void
    {
        if (! app()->runningUnitTests()
            && $this->supports($model)
            && config('database.default') === 'sqlite') {
            AutoSyncService::syncIfLocal();
        }
    }

    private function supports(Model $model): bool
    {
        $connection = $model->getConnectionName();
        $schema = Schema::connection($connection);

        return OfflineSyncRegistry::has($model->getTable())
            && $schema->hasTable($model->getTable())
            && $schema->hasColumn($model->getTable(), 'sync_uuid')
            && $schema->hasColumn($model->getTable(), 'is_synced');
    }

    private function displayName(Model $model): ?string
    {
        foreach (['name', 'check_number', 'order_number', 'shift_number', 'receipt_number', 'submission_number', 'key'] as $column) {
            if ($model->getAttribute($column)) {
                return (string) $model->getAttribute($column);
            }
        }

        return null;
    }
}
