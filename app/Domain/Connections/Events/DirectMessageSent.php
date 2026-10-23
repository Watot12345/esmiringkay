<?php

namespace App\Domain\Connections\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $connectionId,
        public int $senderId,
        public int $recipientId,
        public string $body
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("connection.{$this->connectionId}"),
            new Channel("user.{$this->recipientId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dm.sent';
    }
}
