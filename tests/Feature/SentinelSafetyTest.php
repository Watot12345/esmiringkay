<?php

use App\Domain\Accounts\Services\TrustScoreService;
use App\Domain\Moderation\Models\Report;
use App\Domain\Moderation\Models\Sanction;
use App\Domain\Moderation\Services\ModerationQueueService;
use App\Domain\Rooms\Events\RoomEnded;
use App\Domain\Rooms\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('sentinel blocks severe hate speech and terminates room', function () {
    Event::fake([RoomEnded::class]);

    $user = User::factory()->create(['trust_score' => 60]);
    $room = Room::create(['vibe' => 'Debate', 'type' => 'text', 'started_at' => now()]);

    $response = $this->actingAs($user)->postJson("/room/{$room->id}/message", [
        'body' => 'I will kill you right now',
    ]);

    $response->assertStatus(403)
        ->assertJson(['flagged' => true]);

    $room->refresh();
    expect($room->ended_at)->not->toBeNull();
    expect($room->end_reason)->toBe('sentinel_flag');

    $user->refresh();
    expect($user->trust_score)->toBeLessThan(60);

    Event::assertDispatched(RoomEnded::class);
});

test('sentinel offers supportive helpline resources on crisis signals', function () {
    $room = Room::create(['vibe' => 'Vent', 'type' => 'text', 'started_at' => now()]);

    $response = $this->postJson("/room/{$room->id}/message", [
        'body' => 'I feel so empty and I want to die',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'flagged' => true,
            'crisis' => true,
        ]);
});

test('trust score service manages tiers and penalties correctly', function () {
    $service = app(TrustScoreService::class);
    $user = User::factory()->create([
        'trust_score' => 80,
        'created_at' => now()->subDays(2),
    ]);

    expect($service->getTrustTier($user))->toBe('trusted');

    // Penalize user
    $service->penalize($user, 45, 'Inappropriate behavior');
    $user->refresh();
    expect($user->trust_score)->toBe(35);
    expect($service->getTrustTier($user))->toBe('standard');

    // Severe penalty drops into restricted
    $service->penalize($user, 20, 'Second violation');
    $user->refresh();
    expect($user->trust_score)->toBe(15);
    expect($user->status)->toBe('restricted');
    expect($service->getTrustTier($user))->toBe('restricted');
});

test('moderator can triage reports by severity and apply sanctions', function () {
    $reporter = User::factory()->create();
    $violator = User::factory()->create(['trust_score' => 60]);

    $report = Report::create([
        'reporter_id' => $reporter->id,
        'reported_id' => $violator->id,
        'category' => 'Hate Speech',
        'note' => 'Spammed slurs in video',
        'severity' => 'critical',
        'status' => 'pending',
    ]);

    $queueService = app(ModerationQueueService::class);
    $pending = $queueService->getPendingQueue();

    expect($pending->first()->id)->toBe($report->id);

    // Apply temp ban sanction
    $sanction = $queueService->applySanction(
        report: $report,
        sanctionType: Sanction::TYPE_TEMP_BAN,
        reason: 'Hate speech in public room',
        durationDays: 7
    );

    expect($sanction->type)->toBe('temp_ban');
    expect($sanction->expires_at)->not->toBeNull();

    $violator->refresh();
    expect($violator->status)->toBe('suspended');
    expect($violator->trust_score)->toBe(25);

    $report->refresh();
    expect($report->status)->toBe('sanctioned');
});

test('user can submit appeal and moderator can approve it', function () {
    $user = User::factory()->create(['status' => 'suspended', 'trust_score' => 30]);

    $sanction = Sanction::create([
        'user_id' => $user->id,
        'type' => 'temp_ban',
        'reason' => 'Misunderstood context',
        'expires_at' => now()->addDays(7),
    ]);

    // Submit appeal via HTTP endpoint
    $response = $this->postJson('/appeal', [
        'sanction_id' => $sanction->id,
        'explanation' => 'It was a misunderstanding with an icebreaker answer.',
    ]);

    $response->assertStatus(200)->assertJson(['success' => true]);

    $sanction->refresh();
    expect($sanction->appeal_status)->toBe('pending');

    // Moderator resolves appeal
    $queueService = app(ModerationQueueService::class);
    $queueService->resolveAppeal($sanction, true, 'Clarified and forgiven');

    $sanction->refresh();
    expect($sanction->appeal_status)->toBe('approved');

    $user->refresh();
    expect($user->status)->toBe('active');
    expect($user->trust_score)->toBe(45); // restored + 15
});
