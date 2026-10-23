<?php

namespace App\Console\Commands;

use App\Domain\Rooms\Models\Message;
use App\Domain\Rooms\Models\Room;
use Illuminate\Console\Command;

class PurgeEphemeralDataCommand extends Command
{
    protected $signature = 'emiringkay:purge-ephemeral {--hours=24 : Age threshold in hours}';

    protected $description = 'Purge ephemeral 1:1 room messages and mark stale sessions as ended (01-context.md & 04-architecture.md)';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $threshold = now()->subHours($hours);

        // 1. Purge ephemeral 1:1 chat messages per privacy mandate
        $deletedMessages = Message::where('created_at', '<', $threshold)->delete();

        // 2. Mark abandoned/stale rooms as ended
        $closedRooms = Room::whereNull('ended_at')
            ->where('started_at', '<', now()->subHours(2))
            ->update([
                'ended_at' => now(),
                'end_reason' => 'timeout_expired',
            ]);

        $this->info("Successfully purged {$deletedMessages} ephemeral messages and closed {$closedRooms} stale rooms.");

        return Command::SUCCESS;
    }
}
