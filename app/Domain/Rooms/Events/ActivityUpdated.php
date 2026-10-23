<?php

namespace App\Domain\Rooms\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomId,
        public string $kind, // doodle, trivia, breathing, icebreaker
        public string $action, // draw, clear, answer, next_question, start, stop
        public array $payload = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("room.{$this->roomId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'activity.updated';
    }
}
