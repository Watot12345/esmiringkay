<?php

namespace App\Domain\Plaza\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlazaRoomUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomId,
        public string $action,
        public array $data = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("plaza.{$this->roomId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'plaza.room.updated';
    }
}
