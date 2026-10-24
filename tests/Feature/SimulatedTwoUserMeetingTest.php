<?php

use App\Domain\Connections\Models\Connection;
use App\Domain\Rooms\Events\KeepRequested;
use App\Domain\Rooms\Events\MessageSent;
use App\Domain\Rooms\Models\Activity;
use App\Domain\Rooms\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('complete simulation: two real users meet, chat, share doodle, and become circle friends', function () {
    Event::fake([MessageSent::class, KeepRequested::class]);

    // 1. Create two distinct users (Alice and Bob)
    $alice = User::factory()->create([
        'name' => 'Alice',
        'display_name' => 'Alice Wanderer',
    ]);

    $bob = User::factory()->create([
        'name' => 'Bob',
        'display_name' => 'Bob Starlight',
    ]);

    // 2. Alice clicks Start matching (Chill vibe)
    $aliceTicketId = 'ticket_alice_123';
    $aliceMatchRes = $this->actingAs($alice)->postJson('/match', [
        'vibe' => 'Chill',
        'mode' => 'text',
        'user_id' => (string) $alice->id,
        'ticket_id' => $aliceTicketId,
    ]);

    $aliceMatchRes->assertStatus(200)
        ->assertJson([
            'status' => 'waiting',
            'ticket_id' => $aliceTicketId,
        ]);

    // 3. Bob clicks Start matching (same Chill vibe)
    $bobTicketId = 'ticket_bob_456';
    $bobMatchRes = $this->actingAs($bob)->postJson('/match', [
        'vibe' => 'Chill',
        'mode' => 'text',
        'user_id' => (string) $bob->id,
        'ticket_id' => $bobTicketId,
    ]);

    $bobMatchRes->assertStatus(200)
        ->assertJson([
            'status' => 'matched',
            'vibe' => 'Chill',
            'mode' => 'text',
        ]);

    $roomId = $bobMatchRes->json('room_id');
    expect($roomId)->not->toBeNull();

    // 4. Alice polls match status and is assigned the EXACT same room
    $alicePollRes = $this->actingAs($alice)->getJson("/match/status?ticket_id={$aliceTicketId}");
    $alicePollRes->assertStatus(200)
        ->assertJson([
            'status' => 'matched',
            'room_id' => $roomId,
        ]);

    // 5. Both Alice and Bob enter the room
    $aliceRoomRes = $this->actingAs($alice)->get("/room/{$roomId}");
    $aliceRoomRes->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Room/Show')->where('room.id', $roomId));

    $bobRoomRes = $this->actingAs($bob)->get("/room/{$roomId}");
    $bobRoomRes->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Room/Show')->where('room.id', $roomId));

    // 6. Alice sends a real message to Bob
    $aliceMsgRes = $this->actingAs($alice)->postJson("/room/{$roomId}/message", [
        'body' => 'Hello Bob! This is a real live connection.',
    ]);
    $aliceMsgRes->assertStatus(200)->assertJson(['success' => true]);

    Event::assertDispatched(MessageSent::class, function ($event) use ($roomId, $alice) {
        return $event->roomId === $roomId
            && $event->senderId === (string) $alice->id
            && $event->body === 'Hello Bob! This is a real live connection.';
    });

    // 7. Bob replies back to Alice
    $bobMsgRes = $this->actingAs($bob)->postJson("/room/{$roomId}/message", [
        'body' => 'Hey Alice! I received your message in real-time.',
    ]);
    $bobMsgRes->assertStatus(200)->assertJson(['success' => true]);

    Event::assertDispatched(MessageSent::class, function ($event) use ($roomId, $bob) {
        return $event->roomId === $roomId
            && $event->senderId === (string) $bob->id
            && $event->body === 'Hey Alice! I received your message in real-time.';
    });

    // 8. Shared Activity: Alice draws on the Doodle Board
    $doodleRes = $this->actingAs($alice)->postJson("/room/{$roomId}/activity", [
        'kind' => 'doodle',
        'action' => 'draw',
        'payload' => [
            'stroke' => [
                'color' => '#5EEAD4',
                'size' => 3,
                'points' => [['x' => 50, 'y' => 50], ['x' => 100, 'y' => 100]],
            ],
        ],
    ]);
    $doodleRes->assertStatus(200);

    // Verify Bob sees Alice's doodle in the room's activity state
    $bobDoodleState = $this->actingAs($bob)->getJson("/room/{$roomId}/activity/doodle");
    $bobDoodleState->assertStatus(200);
    expect($bobDoodleState->json('state.strokes'))->toHaveCount(1);
    expect($bobDoodleState->json('state.strokes.0.color'))->toBe('#5EEAD4');

    // 9. Mutual Keeps: Both Alice and Bob request to Keep each other as Friend
    $aliceKeepRes = $this->actingAs($alice)->postJson("/room/{$roomId}/keep", [
        'type' => 'friend',
        'target_user_id' => $bob->id,
    ]);
    $aliceKeepRes->assertStatus(200);

    $bobKeepRes = $this->actingAs($bob)->postJson("/room/{$roomId}/keep", [
        'type' => 'friend',
        'target_user_id' => $alice->id,
    ]);
    $bobKeepRes->assertStatus(200);

    // Mutual match resolution triggers a Connection in Circle
    $this->assertDatabaseHas('connections', [
        'user_a' => $alice->id,
        'user_b' => $bob->id,
        'type' => 'friend',
        'status' => 'mutual',
    ]);

    // 10. Check Alice's Circle to confirm Bob appears as an active friend
    $aliceCircle = $this->actingAs($alice)->get('/circle');
    $aliceCircle->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Circle/Index')
            ->has('connections', 1)
            ->where('connections.0.partner.name', 'Bob Starlight')
        );
});
