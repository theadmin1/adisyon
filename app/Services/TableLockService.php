<?php

namespace App\Services;

use App\Events\TableStatusUpdated;
use App\Models\DiningTable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;

class TableLockService
{
    /**
     * @return array<string, mixed>
     */
    public function acquire(DiningTable $table, string $lockedBy, ?int $userId = null, ?string $actorName = null): array
    {
        $existingLock = $this->get($table);

        if ($existingLock !== null && ! $this->ownedBy($existingLock, $lockedBy, $userId, $actorName)) {
            $existingLock['conflict'] = true;

            return $existingLock;
        }

        return $this->lock($table, $lockedBy, $userId, $actorName);
    }

    public function lock(DiningTable $table, string $lockedBy, ?int $userId = null, ?string $actorName = null): array
    {
        $ttlSeconds = max(30, (int) config('adisyon.table_lock_ttl_seconds', 180));
        $lockPayload = [
            'table_id' => (int) $table->id,
            'branch_id' => (int) $table->branch_id,
            'is_locked' => true,
            'locked_by' => $lockedBy,
            'user_id' => $userId,
            'actor_name' => $actorName,
            'locked_at' => now()->toIso8601String(),
            'expires_at' => now()->addSeconds($ttlSeconds)->toIso8601String(),
        ];

        Cache::put($this->key((int) $table->id), $lockPayload, now()->addSeconds($ttlSeconds));

        $this->broadcastTableStatus($table, $lockPayload);

        return $lockPayload;
    }

    public function unlock(DiningTable $table): void
    {
        Cache::forget($this->key((int) $table->id));
        $this->broadcastTableStatus($table, null);
    }

    public function releaseIfOwnedBy(DiningTable $table, string $lockedBy, ?int $userId = null, ?string $actorName = null): void
    {
        $existingLock = $this->get($table);

        if ($existingLock === null || ! $this->ownedBy($existingLock, $lockedBy, $userId, $actorName)) {
            return;
        }

        $this->unlock($table);
    }

    /**
     * @return array{is_locked: bool, locked_by: ?string, actor_name: ?string, user_id: ?int, locked_at: ?string, lock_expires_at: ?string}
     */
    public function stateForTable(DiningTable $table): array
    {
        $lock = $this->get($table);

        return [
            'is_locked' => $lock !== null,
            'locked_by' => $lock['locked_by'] ?? null,
            'actor_name' => $lock['actor_name'] ?? null,
            'user_id' => isset($lock['user_id']) ? (int) $lock['user_id'] : null,
            'locked_at' => $lock['locked_at'] ?? null,
            'lock_expires_at' => $lock['expires_at'] ?? null,
        ];
    }

    public function isLocked(DiningTable|int $table): bool
    {
        return $this->get($table) !== null;
    }

    public function ensureUnlocked(DiningTable|int $table, string $message = 'Bu masayla kasa işlem yapmaktadır.'): void
    {
        $lock = $this->get($table);
        if ($lock === null) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'code' => 'TABLE_LOCKED',
        ], 409));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(DiningTable|int $table): ?array
    {
        $lock = Cache::get($this->key($table instanceof DiningTable ? (int) $table->id : (int) $table));

        return is_array($lock) && ($lock['is_locked'] ?? false) ? $lock : null;
    }

    public function isOwnedByCurrentActor(?array $lock, string $lockedBy, ?int $userId = null, ?string $actorName = null): bool
    {
        return $lock !== null && $this->ownedBy($lock, $lockedBy, $userId, $actorName);
    }

    /**
     * @param  array<string, mixed>|null  $lockPayload
     */
    private function broadcastTableStatus(DiningTable $table, ?array $lockPayload): void
    {
        TableStatusUpdated::dispatch(
            (int) $table->branch_id,
            (int) $table->id,
            $table->status?->value ?? (string) $table->status,
            $lockPayload !== null,
            $lockPayload['locked_by'] ?? null,
        );
    }

    private function key(int $tableId): string
    {
        return "table_lock_{$tableId}";
    }

    /**
     * @param  array<string, mixed>  $lock
     */
    private function ownedBy(array $lock, string $lockedBy, ?int $userId = null, ?string $actorName = null): bool
    {
        if (($lock['locked_by'] ?? null) !== $lockedBy) {
            return false;
        }

        if ($userId !== null && isset($lock['user_id']) && (int) $lock['user_id'] === $userId) {
            return true;
        }

        if ($actorName !== null && $actorName !== '' && ($lock['actor_name'] ?? null) === $actorName) {
            return true;
        }

        return false;
    }
}
