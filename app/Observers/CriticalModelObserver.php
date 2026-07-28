<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliveryIntegration;
use App\Models\Device;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\License;
use App\Models\Product;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class CriticalModelObserver
{
    /**
     * @var array<class-string<Model>, array{key: string, category: string, fields: array<int, string>}>
     */
    private const MODELS = [
        Product::class => [
            'key' => 'product',
            'category' => 'catalog',
            'fields' => ['category_id', 'name', 'sku', 'price', 'discounted_price', 'min_stock_level', 'unit', 'track_stock', 'kitchen_department', 'is_active'],
        ],
        Category::class => [
            'key' => 'category',
            'category' => 'catalog',
            'fields' => ['name', 'sort_order', 'is_active'],
        ],
        StaffProfile::class => [
            'key' => 'staff',
            'category' => 'staff',
            'fields' => ['branch_id', 'name', 'role', 'avatar_color', 'is_active', 'pin_length', 'pin_code', 'pin_hash'],
        ],
        RolePermission::class => [
            'key' => 'role_permission',
            'category' => 'staff',
            'fields' => ['role_name', 'permissions'],
        ],
        Setting::class => [
            'key' => 'setting',
            'category' => 'settings',
            'fields' => ['branch_id', 'group', 'key', 'value'],
        ],
        DeliveryIntegration::class => [
            'key' => 'delivery_integration',
            'category' => 'integration',
            'fields' => ['branch_id', 'channel', 'store_name', 'store_id', 'api_key', 'api_secret', 'is_active', 'auto_accept'],
        ],
        Branch::class => [
            'key' => 'branch',
            'category' => 'administration',
            'fields' => ['name', 'code', 'contact_email', 'phone', 'address', 'is_active'],
        ],
        License::class => [
            'key' => 'license',
            'category' => 'administration',
            'fields' => ['branch_id', 'status', 'expires_at', 'max_devices', 'restrictions', 'notes'],
        ],
        Device::class => [
            'key' => 'device',
            'category' => 'administration',
            'fields' => ['branch_id', 'license_id', 'device_code', 'status', 'os_info', 'app_version'],
        ],
        DiningTable::class => [
            'key' => 'table',
            'category' => 'tables',
            'fields' => ['branch_id', 'hall_id', 'name', 'code', 'capacity', 'is_active', 'notes'],
        ],
        Hall::class => [
            'key' => 'hall',
            'category' => 'tables',
            'fields' => ['branch_id', 'name', 'code', 'sort_order', 'is_active'],
        ],
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return array<int, class-string<Model>>
     */
    public static function observedModels(): array
    {
        return array_keys(self::MODELS);
    }

    public function created(Model $model): void
    {
        $this->record($model, 'created', [], $this->values($model));
    }

    public function updated(Model $model): void
    {
        $configuration = $this->configuration($model);
        $changedFields = array_values(array_intersect($configuration['fields'], array_keys($model->getChanges())));

        if ($changedFields === []) {
            return;
        }

        $oldValues = [];
        $newValues = [];

        foreach ($changedFields as $field) {
            $oldValues[$field] = $this->protectSettingValue($model, $field, $model->getRawOriginal($field));
            $newValues[$field] = $this->protectSettingValue($model, $field, $model->getAttribute($field));
        }

        $this->record($model, 'updated', $oldValues, $newValues);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $this->values($model), []);
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function record(Model $model, string $event, array $oldValues, array $newValues): void
    {
        $configuration = $this->configuration($model);

        $this->auditLogger->record(
            action: $configuration['key'].'.'.$event,
            subject: $model,
            oldValues: $oldValues,
            newValues: $newValues,
            category: $configuration['category'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Model $model): array
    {
        $values = [];

        foreach ($this->configuration($model)['fields'] as $field) {
            $values[$field] = $this->protectSettingValue($model, $field, $model->getAttribute($field));
        }

        return $values;
    }

    private function protectSettingValue(Model $model, string $field, mixed $value): mixed
    {
        if ($model instanceof Setting
            && $field === 'value'
            && preg_match('/(?:password|secret|token|api[_-]?key|private[_-]?key|pin|credential|license[_-]?key)/i', (string) $model->key) === 1) {
            return '[REDACTED]';
        }

        return $value;
    }

    /**
     * @return array{key: string, category: string, fields: array<int, string>}
     */
    private function configuration(Model $model): array
    {
        return self::MODELS[$model::class];
    }
}
