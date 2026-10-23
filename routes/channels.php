<?php

use App\Domain\Rooms\Models\Room;
use Illuminate\Support\Facades\Broadcast;

// User private channel for MatchFound and personal alerts
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user?->id === (int) $id;
});

// Room private channel for 1:1 live session (chat, reactions, whispers)
Broadcast::channel('room.{id}', function ($user, $id) {
    // If authenticated, ensure user is a participant or room exists
    // For anonymous MVP guests, allow access to active room
    $room = Room::find($id);

    return $room && is_null($room->ended_at);
});

// Plaza presence channel
Broadcast::channel('plaza.{id}', function ($user) {
    return [
        'id' => $user?->id ?? uniqid(),
        'name' => $user?->display_name ?? 'Stranger',
    ];
});
