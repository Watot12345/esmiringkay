<?php

namespace App\Domain\Rooms\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRtcSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomId,
        public string $senderId,
        public string $signalType, // offer, answer, candidate
        public array $payload
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("room.{$this->roomId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webrtc.signal';
    }
}
