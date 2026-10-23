<?php

use App\Domain\Connections\Events\DirectMessageSent;
use App\Domain\Connections\Events\KeepMatched;
use App\Domain\Connections\Models\Connection;
use App\Domain\Connections\Services\ConnectionService;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('circle index renders mutual keeps list', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create(['name' => 'Luna']);

    Connection::create([
        'user_a' => $userA->id,
        'user_b' => $userB->id,
        'type' => 'friend',
        'status' => 'mutual',
    ]);

    $response = $this->actingAs($userA)->get('/circle');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Circle/Index')
        ->has('connections', 1)
        ->where('connections.0.type', 'friend')
    );
});

test('mutual keep forms and broadcasts KeepMatched when both users opt in', function () {
    Event::fake([KeepMatched::class]);

    $service = app(ConnectionService::class);
    $userA = User::factory()->create(['trust_score' => 60]);
    $userB = User::factory()->create(['trust_score' => 60]);

    // User A requests Keep as friend
    $first = $service->requestKeep($userA, $userB->id, 'friend');
    expect($first['is_mutual'])->toBeFalse();
    expect($first['connection']->status)->toBe('pending');

    // User B also requests Keep as friend -> Mutual match!
    $second = $service->requestKeep($userB, $userA->id, 'friend');
    expect($second['is_mutual'])->toBeTrue();
    expect($second['connection']->status)->toBe('mutual');
    expect($second['connection']->type)->toBe('friend');

    // Check Trust Score reward (+3 each per PRD)
    $userA->refresh();
    $userB->refresh();
    expect($userA->trust_score)->toBe(63);
    expect($userB->trust_score)->toBe(63);

    Event::assertDispatched(KeepMatched::class);
});

test('spark requires mutual opt-in and falls back to friend if one-sided', function () {
    $service = app(ConnectionService::class);
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // User A requests spark
    $service->requestKeep($userA, $userB->id, 'spark');

    // User B requests friend (not spark)
    $second = $service->requestKeep($userB, $userA->id, 'friend');

    expect($second['is_mutual'])->toBeTrue();
    expect($second['connection']->type)->toBe('friend'); // Safe fallback
});

test('user can send direct message to mutual keep', function () {
    Event::fake([DirectMessageSent::class]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conn = Connection::create([
        'user_a' => $userA->id,
        'user_b' => $userB->id,
        'type' => 'confidant',
        'status' => 'mutual',
    ]);

    $response = $this->actingAs($userA)->postJson("/circle/{$conn->id}/message", [
        'body' => 'Hey, thanks for listening earlier!',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => [
                'body' => 'Hey, thanks for listening earlier!',
                'is_me' => true,
            ],
        ]);

    $this->assertDatabaseHas('direct_messages', [
        'connection_id' => $conn->id,
        'sender_id' => $userA->id,
        'body' => 'Hey, thanks for listening earlier!',
    ]);

    Event::assertDispatched(DirectMessageSent::class);
});

test('user cannot send direct message if connection is ended or not mutual', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conn = Connection::create([
        'user_a' => $userA->id,
        'user_b' => $userB->id,
        'type' => 'friend',
        'status' => 'pending', // Not yet mutual
    ]);

    $response = $this->actingAs($userA)->postJson("/circle/{$conn->id}/message", [
        'body' => 'Are you there?',
    ]);

    $response->assertStatus(404);
});

test('either user can end connection instantly', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conn = Connection::create([
        'user_a' => $userA->id,
        'user_b' => $userB->id,
        'type' => 'rival',
        'status' => 'mutual',
    ]);

    $response = $this->actingAs($userB)->postJson("/circle/{$conn->id}/end");

    $response->assertStatus(200)->assertJson(['success' => true]);

    $conn->refresh();
    expect($conn->status)->toBe('ended');
});
