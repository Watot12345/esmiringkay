<?php

namespace App\Domain\Connections\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KeepMatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $connectionId,
        public int $userA,
        public int $userB,
        public string $type
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("user.{$this->userA}"),
            new Channel("user.{$this->userB}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'keep.matched';
    }
}
