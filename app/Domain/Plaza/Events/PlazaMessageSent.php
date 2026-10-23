<?php

namespace App\Domain\Plaza\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlazaMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomId,
        public int $senderId,
        public string $senderName,
        public string $body,
        public string $timestamp
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("plaza.{$this->roomId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'plaza.message';
    }
}
