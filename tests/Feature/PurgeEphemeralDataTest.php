<?php

use App\Domain\Rooms\Models\Message;
use App\Domain\Rooms\Models\Room;
use App\Models\User;

test('purge ephemeral data command deletes old messages and closes abandoned rooms', function () {
    $user = User::factory()->create();

    $staleRoom = Room::create([
        'vibe' => 'Chill',
        'type' => 'text',
        'started_at' => now()->subHours(3),
    ]);

    $oldMessage = Message::create([
        'room_id' => $staleRoom->id,
        'user_id' => $user->id,
        'body' => 'Old ephemeral chat',
    ]);
    $oldMessage->created_at = now()->subHours(25);
    $oldMessage->saveQuietly();

    $freshMessage = Message::create([
        'room_id' => $staleRoom->id,
        'user_id' => $user->id,
        'body' => 'Recent chat',
        'created_at' => now()->subMinutes(10),
    ]);

    $this->artisan('emiringkay:purge-ephemeral --hours=24')
        ->assertSuccessful();

    // Verify old message is purged, fresh message remains
    $this->assertDatabaseMissing('messages', ['id' => $oldMessage->id]);
    $this->assertDatabaseHas('messages', ['id' => $freshMessage->id]);

    // Verify stale room was closed
    $staleRoom->refresh();
    expect($staleRoom->ended_at)->not->toBeNull();
    expect($staleRoom->end_reason)->toBe('timeout_expired');
});
