<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $branchId,
        public readonly int $tableId,
        public readonly string $status,
        public readonly bool $isLocked,
        public readonly ?string $lockedBy = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("waiter.branch.{$this->branchId}")];
    }

    public function broadcastAs(): string
    {
        return 'table.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'table_id' => $this->tableId,
            'status' => $this->status,
            'is_locked' => $this->isLocked,
            'locked_by' => $this->lockedBy,
            'emitted_at' => now()->toIso8601String(),
        ];
    }
}
