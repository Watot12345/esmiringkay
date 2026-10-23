<?php

use App\Domain\Rooms\Models\Room;

test('home meet screen renders successfully', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->has('onlineCount')
        ->has('defaultVibe')
    );
});

test('user can initiate matching with vibe and mode', function () {
    $response = $this->postJson('/match', [
        'vibe' => 'Chill',
        'mode' => 'text',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'matched',
            'vibe' => 'Chill',
            'mode' => 'text',
        ]);

    $this->assertDatabaseHas('rooms', [
        'vibe' => 'Chill',
        'type' => 'text',
    ]);
});

test('room live session can be viewed', function () {
    $room = Room::create([
        'vibe' => 'Vent',
        'type' => 'text',
        'started_at' => now(),
    ]);

    $response = $this->get("/room/{$room->id}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Room/Show')
        ->has('room.id')
        ->where('room.vibe', 'Vent')
    );
});
