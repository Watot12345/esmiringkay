<?php

use App\Domain\Matching\Services\MatchingEngine;
use App\Domain\Rooms\Events\KeepRequested;
use App\Domain\Rooms\Events\MessageSent;
use App\Domain\Rooms\Events\RoomEnded;
use App\Domain\Rooms\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Event;

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

test('matching engine enforces 7-day exclusion rule (FR-12)', function () {
    $engine = app(MatchingEngine::class);

    $userA = 'user_123';
    $userB = 'user_456';

    expect($engine->isExcluded($userA, $userB))->toBeFalse();

    $engine->recordSkip($userA, $userB);

    expect($engine->isExcluded($userA, $userB))->toBeTrue();
    expect($engine->isExcluded($userB, $userA))->toBeTrue();
});

test('user can broadcast a message in live room', function () {
    Event::fake([MessageSent::class]);

    $room = Room::create([
        'vibe' => 'Play',
        'type' => 'text',
        'started_at' => now(),
    ]);

    $response = $this->postJson("/room/{$room->id}/message", [
        'body' => 'Hello there!',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    Event::assertDispatched(MessageSent::class, function ($event) use ($room) {
        return $event->roomId === $room->id && $event->body === 'Hello there!';
    });
});

test('user can skip and room is marked as ended', function () {
    Event::fake([RoomEnded::class]);

    $room = Room::create([
        'vibe' => 'Debate',
        'type' => 'text',
        'started_at' => now(),
    ]);

    $response = $this->postJson("/room/{$room->id}/skip");

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $room->refresh();
    expect($room->ended_at)->not->toBeNull();
    expect($room->end_reason)->toBe('skip');

    Event::assertDispatched(RoomEnded::class);
});

test('reporting abuse ends the room immediately (FR-40, FR-41)', function () {
    Event::fake([RoomEnded::class]);

    $user = User::factory()->create();

    $room = Room::create([
        'vibe' => 'Vent',
        'type' => 'text',
        'started_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson("/room/{$room->id}/report", [
        'category' => 'Harassment',
        'note' => 'Inappropriate behavior',
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('reports', [
        'room_id' => $room->id,
        'category' => 'Harassment',
        'severity' => 'high',
    ]);

    $room->refresh();
    expect($room->ended_at)->not->toBeNull();
    expect($room->end_reason)->toBe('report');

    Event::assertDispatched(RoomEnded::class);
});

test('user can send a keep request', function () {
    Event::fake([KeepRequested::class]);

    $room = Room::create([
        'vibe' => 'Spark',
        'type' => 'text',
        'started_at' => now(),
    ]);

    $response = $this->postJson("/room/{$room->id}/keep", [
        'type' => 'spark',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'type' => 'spark',
        ]);

    Event::assertDispatched(KeepRequested::class, function ($event) use ($room) {
        return $event->roomId === $room->id && $event->keepType === 'spark';
    });
});
