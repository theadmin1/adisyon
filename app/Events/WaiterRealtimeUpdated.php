<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class WaiterRealtimeUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $eventId;

    /**
     * @param  array<int, string>  $topics
     * @param  array<string, int|null>  $references
     */
    public function __construct(
        public readonly int $branchId,
        public readonly array $topics,
        public readonly string $action,
        public readonly array $references = [],
    ) {
        $this->eventId = (string) Str::uuid();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("waiter.branch.{$this->branchId}")];
    }

    public function broadcastAs(): string
    {
        return 'waiter.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'branch_id' => $this->branchId,
            'topics' => array_values(array_unique($this->topics)),
            'action' => $this->action,
            'references' => array_filter(
                $this->references,
                static fn (?int $value): bool => $value !== null,
            ),
            'emitted_at' => now()->toIso8601String(),
        ];
    }
}
