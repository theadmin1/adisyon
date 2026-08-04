<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenItemStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $branchId,
        public readonly int $orderId,
        public readonly string $tableName,
        public readonly int $itemId,
        public readonly string $status,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("waiter.branch.{$this->branchId}")];
    }

    public function broadcastAs(): string
    {
        return 'kitchen.item.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'table_name' => $this->tableName,
            'item_id' => $this->itemId,
            'status' => $this->status,
            'emitted_at' => now()->toIso8601String(),
        ];
    }
}
