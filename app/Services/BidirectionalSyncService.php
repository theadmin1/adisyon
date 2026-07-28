<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class BidirectionalSyncService
{
    /**
     * Build the additional server snapshot and the authoritative UUID manifest.
     *
     * @return array{resources: array<string, array<int, array<string, mixed>>>, manifest: array<string, array<int, string>>}
     */
    public function buildPullPayload(int $branchId, ?string $connectionName = null): array
    {
        $connectionName ??= config('database.default');
        $resources = [];

        foreach (OfflineSyncRegistry::genericPullResources() as $resource => $configuration) {
            if (! $this->resourceAvailable($connectionName, $resource)) {
                continue;
            }

            $this->ensureUuids($connectionName, $resource, $branchId);
            $resources[$resource] = $this->resourceQuery($connectionName, $resource, $branchId)
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => $this->serializeRow(
                    $connectionName,
                    $resource,
                    $row,
                    $branchId
                ))
                ->all();
        }

        $manifest = [];
        foreach (OfflineSyncRegistry::all() as $resource => $configuration) {
            if (($configuration['mode'] ?? null) === 'push_only'
                || ! $this->resourceAvailable($connectionName, $resource)) {
                continue;
            }

            $this->ensureUuids($connectionName, $resource, $branchId);
            $manifest[$resource] = $this->resourceQuery($connectionName, $resource, $branchId)
                ->whereNotNull('sync_uuid')
                ->pluck('sync_uuid')
                ->filter()
                ->values()
                ->all();
        }

        return compact('resources', 'manifest');
    }

    /**
     * @return array{resources: array<string, array<int, array<string, mixed>>>, deleted_resources: array<int, array{resource: string, sync_uuid: string}>}
     */
    public function collectLocalChanges(int $branchId): array
    {
        $connectionName = 'sqlite';
        $resources = [];

        foreach (OfflineSyncRegistry::genericPushResources() as $resource => $configuration) {
            if (! $this->resourceAvailable($connectionName, $resource)) {
                continue;
            }

            $rows = $this->resourceQuery($connectionName, $resource, $branchId)
                ->where(function (Builder $query): void {
                    $query->whereNull('is_synced')
                        ->orWhere('is_synced', false)
                        ->orWhere('is_synced', 0);
                })
                ->orderBy('id')
                ->get();

            if ($rows->isNotEmpty()) {
                $resources[$resource] = $rows
                    ->map(fn (object $row): array => $this->serializeRow(
                        $connectionName,
                        $resource,
                        $row,
                        $branchId
                    ))
                    ->all();
            }
        }

        $deletedResources = [];
        if (Schema::connection($connectionName)->hasTable('deleted_records')) {
            $tombstones = DB::connection($connectionName)->table('deleted_records')
                ->where(function (Builder $query): void {
                    $query->whereNull('is_synced')
                        ->orWhere('is_synced', false)
                        ->orWhere('is_synced', 0);
                })
                ->get();

            foreach ($tombstones as $tombstone) {
                $resource = OfflineSyncRegistry::normalizeDeletedType((string) $tombstone->type);
                if (! $resource || ! $tombstone->sync_uuid) {
                    continue;
                }

                // Product/category deletes remain in the legacy payload too. Sending
                // them here is harmless and makes the generic protocol complete.
                $deletedResources[] = [
                    'resource' => $resource,
                    'sync_uuid' => (string) $tombstone->sync_uuid,
                ];
            }
        }

        return [
            'resources' => $resources,
            'deleted_resources' => $deletedResources,
        ];
    }

    /**
     * Apply device changes to the server in dependency order.
     *
     * @param  array<string, mixed>  $resources
     * @param  array<int, mixed>  $deletedResources
     * @return array<int, string>
     */
    public function applyPush(
        int $branchId,
        array $resources,
        array $deletedResources,
        ?string $connectionName = null
    ): array {
        $connectionName ??= config('database.default');
        $syncedUuids = [];
        $allowed = OfflineSyncRegistry::genericPushResources();

        foreach ($allowed as $resource => $configuration) {
            if (! $this->resourceAvailable($connectionName, $resource)) {
                continue;
            }

            $records = $resources[$resource] ?? [];
            if (! is_array($records)) {
                throw new RuntimeException("Geçersiz senkronizasyon kaynağı: {$resource}");
            }

            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $syncUuid = $this->validUuid($record['sync_uuid'] ?? null);
                if (! $syncUuid) {
                    continue;
                }

                $this->upsertRecord(
                    $connectionName,
                    $resource,
                    $record,
                    $branchId,
                    preserveUnsynced: false
                );
                $syncedUuids[] = $syncUuid;
            }
        }

        $deleteGroups = [];
        foreach ($deletedResources as $deleted) {
            if (! is_array($deleted)) {
                continue;
            }

            $resource = OfflineSyncRegistry::normalizeDeletedType((string) ($deleted['resource'] ?? ''));
            $syncUuid = $this->validUuid($deleted['sync_uuid'] ?? null);
            if ($resource && $syncUuid) {
                $deleteGroups[$resource][] = $syncUuid;
            }
        }

        foreach (array_reverse(array_keys(OfflineSyncRegistry::all())) as $resource) {
            if (empty($deleteGroups[$resource])
                || ! $this->resourceAvailable($connectionName, $resource)) {
                continue;
            }

            foreach (array_unique($deleteGroups[$resource]) as $syncUuid) {
                $query = $this->resourceQuery($connectionName, $resource, $branchId)
                    ->where('sync_uuid', $syncUuid);
                $query->delete();

                if (! $this->resourceQuery($connectionName, $resource, $branchId)
                    ->where('sync_uuid', $syncUuid)
                    ->exists()) {
                    $syncedUuids[] = $syncUuid;
                }
            }
        }

        return array_values(array_unique($syncedUuids));
    }

    /**
     * Apply the cloud snapshot locally and remove records deleted online.
     *
     * @param  array<string, mixed>  $resources
     * @param  array<string, mixed>  $manifest
     */
    public function applyPull(int $branchId, array $resources, array $manifest): void
    {
        $connectionName = 'sqlite';

        DB::connection($connectionName)->transaction(function () use (
            $branchId,
            $resources,
            $manifest,
            $connectionName
        ): void {
            foreach (OfflineSyncRegistry::genericPullResources() as $resource => $configuration) {
                if (! $this->resourceAvailable($connectionName, $resource)) {
                    continue;
                }

                $records = $resources[$resource] ?? [];
                if (! is_array($records)) {
                    continue;
                }

                foreach ($records as $record) {
                    if (is_array($record)) {
                        $this->upsertRecord(
                            $connectionName,
                            $resource,
                            $record,
                            $branchId,
                            preserveUnsynced: true
                        );
                    }
                }
            }

            // Children are cleaned first so local FK constraints cannot preserve
            // an online-deleted parent accidentally.
            foreach (array_reverse(array_keys(OfflineSyncRegistry::all())) as $resource) {
                if (! array_key_exists($resource, $manifest)
                    || ! is_array($manifest[$resource])
                    || ! $this->resourceAvailable($connectionName, $resource)) {
                    continue;
                }

                $serverUuids = array_values(array_filter(
                    $manifest[$resource],
                    fn (mixed $uuid): bool => is_string($uuid) && $uuid !== ''
                ));

                $query = $this->resourceQuery($connectionName, $resource, $branchId)
                    ->where('is_synced', true);
                if ($serverUuids !== []) {
                    $query->whereNotIn('sync_uuid', $serverUuids);
                }
                $query->delete();
            }

            $this->reconcileTombstones($manifest);
        });
    }

    /**
     * @param  array<int, string>  $syncedUuids
     */
    public function markLocalChangesSynced(array $syncedUuids): void
    {
        $syncedUuids = array_values(array_unique(array_filter($syncedUuids)));
        if ($syncedUuids === []) {
            return;
        }

        foreach (OfflineSyncRegistry::genericPushResources() as $resource => $configuration) {
            if ($this->resourceAvailable('sqlite', $resource)) {
                DB::connection('sqlite')->table($resource)
                    ->whereIn('sync_uuid', $syncedUuids)
                    ->update(['is_synced' => true]);
            }
        }

        if (Schema::connection('sqlite')->hasTable('deleted_records')) {
            DB::connection('sqlite')->table('deleted_records')
                ->whereIn('sync_uuid', $syncedUuids)
                ->update(['is_synced' => true, 'updated_at' => now()]);
        }
    }

    private function upsertRecord(
        string $connectionName,
        string $resource,
        array $record,
        int $branchId,
        bool $preserveUnsynced
    ): void {
        $syncUuid = $this->validUuid($record['sync_uuid'] ?? null);
        if (! $syncUuid) {
            return;
        }

        $configuration = OfflineSyncRegistry::get($resource);
        $columns = Schema::connection($connectionName)->getColumnListing($resource);
        $columnLookup = array_fill_keys($columns, true);

        $query = $this->resourceQuery($connectionName, $resource, $branchId);
        $existing = (clone $query)->where('sync_uuid', $syncUuid)->first();

        $sourceId = filter_var($record['_source_id'] ?? null, FILTER_VALIDATE_INT);
        if (! $existing && $sourceId) {
            $sourceMatch = (clone $query)->where('id', $sourceId)->first();
            if ($sourceMatch && (! $sourceMatch->sync_uuid || $sourceMatch->sync_uuid === $syncUuid)) {
                $existing = $sourceMatch;
            }
        }

        $values = [];
        foreach ($record as $column => $value) {
            if (str_starts_with((string) $column, '_')
                || in_array($column, ['id', 'branch_id', 'sync_uuid', 'is_synced', 'synced_at'], true)
                || str_ends_with((string) $column, '_id')
                || ! isset($columnLookup[$column])
                || in_array($column, $configuration['hidden'] ?? [], true)) {
                continue;
            }
            $values[$column] = $value;
        }

        foreach (($configuration['references'] ?? []) as $foreignKey => $targetResource) {
            if (! isset($columnLookup[$foreignKey])) {
                continue;
            }

            $relatedUuid = $record['_relations'][$foreignKey] ?? null;
            $values[$foreignKey] = $relatedUuid
                ? $this->resourceQuery($connectionName, $targetResource, $branchId)
                    ->where('sync_uuid', $relatedUuid)
                    ->value('id')
                : null;
        }

        if (! $existing) {
            $natural = $configuration['natural'] ?? [];
            if ($natural !== [] && collect($natural)->every(
                fn (string $column): bool => array_key_exists($column, $values) && $values[$column] !== null && $values[$column] !== ''
            )) {
                $naturalQuery = clone $query;
                foreach ($natural as $column) {
                    $naturalQuery->where($column, $values[$column]);
                }
                $existing = $naturalQuery->first();
            }
        }

        if ($existing && $preserveUnsynced
            && property_exists($existing, 'is_synced')
            && ! (bool) $existing->is_synced) {
            return;
        }

        $values['sync_uuid'] = $syncUuid;
        $values['is_synced'] = true;
        if (OfflineSyncRegistry::branchScoped($resource) && isset($columnLookup['branch_id'])) {
            $values['branch_id'] = $branchId;
        }

        if ($existing) {
            DB::connection($connectionName)->table($resource)
                ->where('id', $existing->id)
                ->update($values);
        } else {
            $values['created_at'] ??= now();
            $values['updated_at'] ??= now();
            DB::connection($connectionName)->table($resource)->insert($values);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(
        string $connectionName,
        string $resource,
        object $row,
        int $branchId
    ): array {
        $configuration = OfflineSyncRegistry::get($resource);
        $data = (array) $row;
        $payload = [
            '_source_id' => $data['id'] ?? null,
            'sync_uuid' => $data['sync_uuid'] ?? null,
            '_relations' => [],
        ];

        foreach ($data as $column => $value) {
            if (in_array($column, ['id', 'branch_id', 'sync_uuid', 'is_synced', 'synced_at'], true)
                || str_ends_with($column, '_id')
                || in_array($column, $configuration['hidden'] ?? [], true)) {
                continue;
            }

            if (($configuration['conditionally_hidden_value'] ?? false)
                && $column === 'value'
                && $this->sensitiveSettingKey((string) ($data['key'] ?? ''))) {
                continue;
            }

            $payload[$column] = $value;
        }

        foreach (($configuration['references'] ?? []) as $foreignKey => $targetResource) {
            $foreignId = $data[$foreignKey] ?? null;
            if (! $foreignId || ! $this->resourceAvailable($connectionName, $targetResource)) {
                $payload['_relations'][$foreignKey] = null;

                continue;
            }

            $this->ensureUuids($connectionName, $targetResource, $branchId);
            $payload['_relations'][$foreignKey] = DB::connection($connectionName)
                ->table($targetResource)
                ->where('id', $foreignId)
                ->value('sync_uuid');
        }

        return $payload;
    }

    private function ensureUuids(string $connectionName, string $resource, int $branchId): void
    {
        if (! $this->resourceAvailable($connectionName, $resource)) {
            return;
        }

        $this->resourceQuery($connectionName, $resource, $branchId)
            ->where(fn (Builder $query) => $query->whereNull('sync_uuid')->orWhere('sync_uuid', ''))
            ->orderBy('id')
            ->eachById(function (object $row) use ($connectionName, $resource): void {
                DB::connection($connectionName)->table($resource)
                    ->where('id', $row->id)
                    ->update([
                        'sync_uuid' => (string) Str::uuid(),
                        'is_synced' => true,
                    ]);
            });
    }

    private function resourceQuery(
        string $connectionName,
        string $resource,
        int $branchId
    ): Builder {
        $query = DB::connection($connectionName)->table($resource);
        if (OfflineSyncRegistry::branchScoped($resource)
            && Schema::connection($connectionName)->hasColumn($resource, 'branch_id')) {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    private function resourceAvailable(string $connectionName, string $resource): bool
    {
        $schema = Schema::connection($connectionName);

        return $schema->hasTable($resource)
            && $schema->hasColumn($resource, 'sync_uuid')
            && $schema->hasColumn($resource, 'is_synced');
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function reconcileTombstones(array $manifest): void
    {
        if (! Schema::connection('sqlite')->hasTable('deleted_records')) {
            return;
        }

        foreach (DB::connection('sqlite')->table('deleted_records')->get() as $tombstone) {
            $resource = OfflineSyncRegistry::normalizeDeletedType((string) $tombstone->type);
            if (! $resource || ! array_key_exists($resource, $manifest)) {
                continue;
            }

            $serverUuids = is_array($manifest[$resource]) ? $manifest[$resource] : [];
            if ($tombstone->sync_uuid && in_array($tombstone->sync_uuid, $serverUuids, true)) {
                DB::connection('sqlite')->table('deleted_records')
                    ->where('id', $tombstone->id)
                    ->update(['is_synced' => false, 'updated_at' => now()]);
            } else {
                DB::connection('sqlite')->table('deleted_records')
                    ->where('id', $tombstone->id)
                    ->delete();
            }
        }
    }

    private function validUuid(mixed $value): ?string
    {
        if (! is_string($value) || ! Str::isUuid($value)) {
            return null;
        }

        return $value;
    }

    private function sensitiveSettingKey(string $key): bool
    {
        return preg_match(
            '/(?:password|secret|token|api[_-]?key|private[_-]?key|pin|credential|license[_-]?key)/i',
            $key
        ) === 1;
    }
}
