<?php

use App\Domain\Moderation\Models\Sanction;
use App\Domain\Plaza\Events\PlazaMessageSent;
use App\Domain\Plaza\Events\PlazaRoomUpdated;
use App\Domain\Plaza\Models\PlazaEvent;
use App\Domain\Plaza\Models\PlazaRoom;
use App\Domain\Plaza\Models\PlazaRoomMember;
use App\Domain\Plaza\Services\PlazaService;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

test('plaza home page renders with rooms, daily prompt, events, and badges', function () {
    $response = $this->get('/plaza');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Plaza/Index')
        ->has('rooms')
        ->has('dailyPrompt.prompt')
        ->has('events')
        ->has('badges.all')
        ->has('currentUser')
    );
});

test('user can host a plaza space with max 8 capacity constraint (FR-31)', function () {
    $user = User::factory()->create(['name' => 'Host User']);

    $response = $this->actingAs($user)->postJson('/plaza/rooms', [
        'topic' => 'Midnight Coding & Lo-Fi Lounge',
        'vibe' => 'Chill',
        'description' => 'Gentle focus space',
        'capacity' => 8, // Enforces FR-31 cap of 8
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('plaza_rooms', [
        'topic' => 'Midnight Coding & Lo-Fi Lounge',
        'vibe' => 'Chill',
        'capacity' => 8,
        'host_id' => $user->id,
    ]);

    $room = PlazaRoom::where('topic', 'Midnight Coding & Lo-Fi Lounge')->first();
    expect($room->activeMembers()->where('user_id', $user->id)->first()->role)->toBe('host');
});

test('user can view and join plaza space', function () {
    $host = User::factory()->create();
    $visitor = User::factory()->create(['name' => 'Night Visitor']);

    $room = PlazaRoom::create([
        'topic' => 'Heart to Heart Venting',
        'vibe' => 'Vent',
        'capacity' => 8,
        'host_id' => $host->id,
        'is_active' => true,
        'starts_at' => now(),
    ]);

    $response = $this->actingAs($visitor)->get("/plaza/rooms/{$room->id}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Plaza/Show')
        ->has('room.id')
        ->has('room.capacity')
        ->has('room.members')
        ->where('currentUser.isHost', false)
    );

    $this->assertDatabaseHas('plaza_room_members', [
        'plaza_room_id' => $room->id,
        'user_id' => $visitor->id,
    ]);
});

test('joining a full plaza room is rejected (FR-31 capacity enforcement)', function () {
    $host = User::factory()->create();

    $room = PlazaRoom::create([
        'topic' => 'Full Space',
        'vibe' => 'Play',
        'capacity' => 8,
        'host_id' => $host->id,
        'is_active' => true,
    ]);

    // Fill room with 8 members
    for ($i = 0; $i < 8; $i++) {
        $u = User::factory()->create();
        PlazaRoomMember::create([
            'plaza_room_id' => $room->id,
            'user_id' => $u->id,
            'role' => 'listener',
            'joined_at' => now(),
        ]);
    }

    $ninthUser = User::factory()->create();

    $service = app(PlazaService::class);

    expect(fn () => $service->joinRoom($room->id, $ninthUser))
        ->toThrow(ValidationException::class);
});

test('sending group message dispatches PlazaMessageSent and verifies Sentinel', function () {
    Event::fake([PlazaMessageSent::class]);

    $user = User::factory()->create();
    $room = PlazaRoom::create([
        'topic' => 'Mindful Talks',
        'vibe' => 'Chill',
        'capacity' => 8,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->postJson("/plaza/rooms/{$room->id}/message", [
        'body' => 'Sending warm vibes to everyone here!',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    Event::assertDispatched(PlazaMessageSent::class, function ($event) use ($room) {
        return $event->roomId === $room->id && $event->body === 'Sending warm vibes to everyone here!';
    });

    // Sentinel flag rejection
    $badResponse = $this->actingAs($user)->postJson("/plaza/rooms/{$room->id}/message", [
        'body' => 'I will kill you if you do not leave',
    ]);

    $badResponse->assertStatus(422);
});

test('host can moderate participants by muting and kicking', function () {
    Event::fake([PlazaRoomUpdated::class]);

    $host = User::factory()->create();
    $member = User::factory()->create();

    $room = PlazaRoom::create([
        'topic' => 'Host Moderation Space',
        'vibe' => 'Chill',
        'capacity' => 8,
        'host_id' => $host->id,
        'is_active' => true,
    ]);

    PlazaRoomMember::create([
        'plaza_room_id' => $room->id,
        'user_id' => $member->id,
        'role' => 'listener',
        'joined_at' => now(),
    ]);

    // Host mutes participant
    $muteRes = $this->actingAs($host)->postJson("/plaza/rooms/{$room->id}/moderate", [
        'target_user_id' => $member->id,
        'action' => 'mute',
    ]);
    $muteRes->assertStatus(200);

    $membership = PlazaRoomMember::where('plaza_room_id', $room->id)->where('user_id', $member->id)->first();
    expect($membership->is_muted)->toBeTrue();

    // Host kicks participant
    $kickRes = $this->actingAs($host)->postJson("/plaza/rooms/{$room->id}/moderate", [
        'target_user_id' => $member->id,
        'action' => 'kick',
    ]);
    $kickRes->assertStatus(200);

    $membership->refresh();
    expect($membership->left_at)->not->toBeNull();
});

test('answering daily prompt records reflection and awards prompt-spark badge (FR-32)', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/plaza/prompt/answer', [
        'answer' => 'Watching the stars outside my window with hot cocoa.',
        'mood_score' => 5,
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('daily_prompt_answers', [
        'user_id' => $user->id,
        'answer' => 'Watching the stars outside my window with hot cocoa.',
        'mood_score' => 5,
    ]);

    // Check badge was awarded
    $this->assertDatabaseHas('user_badges', [
        'user_id' => $user->id,
    ]);
});

test('user can rsvp to upcoming plaza community event', function () {
    $event = PlazaEvent::create([
        'title' => 'Lo-Fi Night Session',
        'scheduled_at' => now()->addHours(3),
        'vibe' => 'Chill',
        'attendees_count' => 5,
    ]);

    $response = $this->postJson("/plaza/events/{$event->id}/rsvp");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'attendees_count' => 6,
        ]);
});

test('sanctioned banned user cannot participate in plaza', function () {
    $bannedUser = User::factory()->create();
    Sanction::create([
        'user_id' => $bannedUser->id,
        'type' => 'temp_ban',
        'reason' => 'Severe misconduct',
        'expires_at' => now()->addDays(7),
    ]);

    $room = PlazaRoom::create([
        'topic' => 'Restricted Space',
        'vibe' => 'Chill',
        'capacity' => 8,
        'is_active' => true,
    ]);

    $response = $this->actingAs($bannedUser)->postJson("/plaza/rooms/{$room->id}/message", [
        'body' => 'Trying to message',
    ]);

    $response->assertStatus(422);
});
