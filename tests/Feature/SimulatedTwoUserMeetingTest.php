<?php

use App\Domain\Connections\Models\Connection;
use App\Domain\Matching\Services\MatchingEngine;
use App\Domain\Rooms\Events\KeepRequested;
use App\Domain\Rooms\Events\MessageSent;
use App\Domain\Rooms\Events\WebRtcSignal;
use App\Domain\Rooms\Models\Activity;
use App\Domain\Rooms\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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

test('webrtc video signaling: 2 devices (one with camera, one without) handshake cleanly without collision', function () {
    Event::fake([WebRtcSignal::class]);

    // 1. Create two users: Device 1 (PC with no camera) and Device 2 (Phone with camera)
    $pcUser = User::factory()->create(['name' => 'PC User']);
    $phoneUser = User::factory()->create(['name' => 'Phone User']);

    $room = Room::create([
        'type' => 'video',
        'vibe' => 'Spark',
        'started_at' => now(),
    ]);

    // 2. PC User enters first -> assigned isInitiator = true
    $pcRoom = $this->actingAs($pcUser)->get("/room/{$room->id}");
    $pcRoom->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Room/Show')
            ->where('isInitiator', true)
            ->where('currentUserId', (string) $pcUser->id)
        );

    // 3. Phone User enters second -> assigned isInitiator = false
    $phoneRoom = $this->actingAs($phoneUser)->get("/room/{$room->id}");
    $phoneRoom->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Room/Show')
            ->where('isInitiator', false)
            ->where('currentUserId', (string) $phoneUser->id)
        );

    // 4. PC User (Initiator) sends SDP Offer
    $offerPayload = ['type' => 'offer', 'sdp' => 'v=0\r\no=- 12345 2 IN IP4 127.0.0.1...'];
    $offerRes = $this->actingAs($pcUser)->postJson("/room/{$room->id}/signal", [
        'signalType' => 'offer',
        'payload' => $offerPayload,
        'sender_id' => (string) $pcUser->id,
    ]);
    $offerRes->assertStatus(200)->assertJson(['success' => true]);

    Event::assertDispatched(WebRtcSignal::class, function ($e) use ($room, $pcUser, $offerPayload) {
        return $e->roomId === $room->id
            && $e->senderId === (string) $pcUser->id
            && $e->signalType === 'offer'
            && $e->payload === $offerPayload;
    });

    // 5. Phone User (Responder) sends SDP Answer
    $answerPayload = ['type' => 'answer', 'sdp' => 'v=0\r\no=- 67890 2 IN IP4 127.0.0.1...'];
    $answerRes = $this->actingAs($phoneUser)->postJson("/room/{$room->id}/signal", [
        'signalType' => 'answer',
        'payload' => $answerPayload,
        'sender_id' => (string) $phoneUser->id,
    ]);
    $answerRes->assertStatus(200)->assertJson(['success' => true]);

    Event::assertDispatched(WebRtcSignal::class, function ($e) use ($room, $phoneUser, $answerPayload) {
        return $e->roomId === $room->id
            && $e->senderId === (string) $phoneUser->id
            && $e->signalType === 'answer'
            && $e->payload === $answerPayload;
    });

    // 6. ICE candidate exchange
    $candidatePayload = ['candidate' => 'candidate:1 1 UDP 2122260223 192.168.1.100 50000 typ host', 'sdpMid' => '0'];
    $candidateRes = $this->actingAs($phoneUser)->postJson("/room/{$room->id}/signal", [
        'signalType' => 'candidate',
        'payload' => $candidatePayload,
        'sender_id' => (string) $phoneUser->id,
    ]);
    $candidateRes->assertStatus(200)->assertJson(['success' => true]);

    Event::assertDispatched(WebRtcSignal::class, function ($e) use ($room, $phoneUser) {
        return $e->roomId === $room->id
            && $e->senderId === (string) $phoneUser->id
            && $e->signalType === 'candidate';
    });
});

test('abandoned waiting tickets are never paired with a new user (no ghost rooms)', function () {
    $engine = app(MatchingEngine::class);

    $engine->enqueue('ghost-ticket', 'ghost-user', 'Vent', 'video');
    Cache::forget('ticket_alive:ghost-ticket');

    $res = $this->postJson('/match', ['vibe' => 'Vent', 'mode' => 'video', 'user_id' => 'real-user']);

    $res->assertOk()->assertJson(['status' => 'waiting']);
});

test('two simultaneous users in the same queue always end up in one room', function () {
    $first = $this->postJson('/match', ['vibe' => 'Play', 'mode' => 'text', 'user_id' => 'u1', 'ticket_id' => 't1']);
    $first->assertJson(['status' => 'waiting']);

    $second = $this->postJson('/match', ['vibe' => 'Play', 'mode' => 'text', 'user_id' => 'u2', 'ticket_id' => 't2']);
    $second->assertJson(['status' => 'matched']);

    $this->getJson('/match/status?ticket_id=t1')
        ->assertJson(['status' => 'matched', 'room_id' => $second->json('room_id')]);
});

test('webrtc sdp payload keeps its trailing CRLF so browsers can parse it', function () {
    Event::fake([WebRtcSignal::class]);

    $room = Room::create(['type' => 'video', 'vibe' => 'Chill', 'started_at' => now()]);
    $sdp = "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\ns=-\r\n";

    $this->postJson("/room/{$room->id}/signal", [
        'signalType' => 'offer',
        'payload' => ['type' => 'offer', 'sdp' => $sdp],
        'sender_id' => 'a',
    ])->assertOk();

    Event::assertDispatched(WebRtcSignal::class, fn ($e) => $e->payload['sdp'] === $sdp);
});
