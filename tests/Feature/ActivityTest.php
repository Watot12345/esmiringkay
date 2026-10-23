<?php

use App\Domain\Rooms\Events\ActivityUpdated;
use App\Domain\Rooms\Models\Activity;
use App\Domain\Rooms\Models\Room;
use Illuminate\Support\Facades\Event;

test('fetching initial doodle activity creates state if not present', function () {
    $room = Room::create([
        'vibe' => 'Chill',
        'type' => 'text',
        'started_at' => now(),
    ]);

    $response = $this->getJson("/room/{$room->id}/activity/doodle");

    $response->assertStatus(200)
        ->assertJson([
            'kind' => 'doodle',
            'state' => [
                'strokes' => [],
            ],
        ]);

    $this->assertDatabaseHas('activities', [
        'room_id' => $room->id,
        'kind' => 'doodle',
    ]);
});

test('submitting a doodle stroke appends stroke and broadcasts event', function () {
    Event::fake([ActivityUpdated::class]);

    $room = Room::create([
        'vibe' => 'Chill',
        'type' => 'text',
        'started_at' => now(),
    ]);

    $stroke = [
        'color' => '#8B7CFF',
        'size' => 4,
        'points' => [['x' => 10, 'y' => 20], ['x' => 12, 'y' => 25]],
    ];

    $response = $this->postJson("/room/{$room->id}/activity", [
        'kind' => 'doodle',
        'action' => 'draw',
        'payload' => ['stroke' => $stroke],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    Event::assertDispatched(ActivityUpdated::class, function ($event) use ($room) {
        return $event->roomId === $room->id
            && $event->kind === 'doodle'
            && $event->action === 'draw';
    });

    $activity = Activity::where('room_id', $room->id)->where('kind', 'doodle')->first();
    expect($activity->state['strokes'])->toHaveCount(1);
    expect($activity->state['strokes'][0]['color'])->toBe('#8B7CFF');
});

test('clearing doodle canvas resets strokes', function () {
    $room = Room::create([
        'vibe' => 'Chill',
        'type' => 'text',
        'started_at' => now(),
    ]);

    // Draw first
    $this->postJson("/room/{$room->id}/activity", [
        'kind' => 'doodle',
        'action' => 'draw',
        'payload' => ['stroke' => ['color' => '#5EEAD4', 'size' => 3, 'points' => []]],
    ]);

    // Clear
    $response = $this->postJson("/room/{$room->id}/activity", [
        'kind' => 'doodle',
        'action' => 'clear',
    ]);

    $response->assertStatus(200);

    $activity = Activity::where('room_id', $room->id)->where('kind', 'doodle')->first();
    expect($activity->state['strokes'])->toBeEmpty();
});

test('trivia duel delivers question and tallies scores correctly', function () {
    Event::fake([ActivityUpdated::class]);

    $room = Room::create([
        'vibe' => 'Play',
        'type' => 'video',
        'started_at' => now(),
    ]);

    $getRes = $this->getJson("/room/{$room->id}/activity/trivia");
    $getRes->assertStatus(200)
        ->assertJsonStructure([
            'kind',
            'state' => ['current_question' => ['question', 'options'], 'scores'],
        ]);

    // User submits correct answer
    $postRes = $this->postJson("/room/{$room->id}/activity", [
        'kind' => 'trivia',
        'action' => 'answer',
        'payload' => [
            'sender' => 'you',
            'is_correct' => true,
        ],
    ]);

    $postRes->assertStatus(200);
    $state = $postRes->json('state');
    expect($state['scores']['you'])->toBe(1);

    Event::assertDispatched(ActivityUpdated::class, function ($event) {
        return $event->kind === 'trivia' && $event->action === 'answer';
    });
});

test('breathing exercise updates phase', function () {
    Event::fake([ActivityUpdated::class]);

    $room = Room::create([
        'vibe' => 'Vent',
        'type' => 'text',
        'started_at' => now(),
    ]);

    $response = $this->postJson("/room/{$room->id}/activity", [
        'kind' => 'breathing',
        'action' => 'sync',
        'payload' => [
            'phase' => 'hold',
        ],
    ]);

    $response->assertStatus(200);
    $state = $response->json('state');
    expect($state['phase'])->toBe('hold');

    Event::assertDispatched(ActivityUpdated::class);
});
