<?php

namespace App\Domain\Plaza\Services;

use App\Domain\Moderation\Models\Sanction;
use App\Domain\Moderation\Services\SentinelService;
use App\Domain\Plaza\Events\PlazaMessageSent;
use App\Domain\Plaza\Events\PlazaRoomUpdated;
use App\Domain\Plaza\Models\Badge;
use App\Domain\Plaza\Models\DailyPrompt;
use App\Domain\Plaza\Models\DailyPromptAnswer;
use App\Domain\Plaza\Models\PlazaEvent;
use App\Domain\Plaza\Models\PlazaMessage;
use App\Domain\Plaza\Models\PlazaRoom;
use App\Domain\Plaza\Models\PlazaRoomMember;
use App\Domain\Plaza\Models\UserBadge;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PlazaService
{
    public function __construct(
        protected SentinelService $sentinel
    ) {}

    /**
     * Get all active Plaza rooms, seeding default community spaces if empty.
     */
    public function getActiveRooms(): Collection
    {
        $rooms = PlazaRoom::with(['host', 'activeMembers.user'])
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->latest()
            ->get();

        if ($rooms->isEmpty()) {
            $this->seedStarterRooms();

            return PlazaRoom::with(['host', 'activeMembers.user'])
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->latest()
                ->get();
        }

        return $rooms;
    }

    /**
     * Create a new Plaza room (Host controls, max 8 participants per FR-31).
     */
    public function createRoom(User $user, array $data): PlazaRoom
    {
        $this->ensureNotSanctioned($user);

        // Cap capacity at 8 according to FR-31
        $capacity = min(max((int) ($data['capacity'] ?? 8), 2), 8);

        $room = PlazaRoom::create([
            'topic' => $data['topic'],
            'vibe' => $data['vibe'] ?? 'Chill',
            'description' => $data['description'] ?? null,
            'host_id' => $user->id,
            'capacity' => $capacity,
            'current_participants' => 1,
            'is_active' => true,
            'starts_at' => now(),
        ]);

        PlazaRoomMember::create([
            'plaza_room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'host',
            'is_muted' => false,
            'joined_at' => now(),
        ]);

        $this->awardBadge($user, 'plaza-host');

        return $room->load(['host', 'activeMembers.user']);
    }

    /**
     * Join an active Plaza room.
     */
    public function joinRoom(string $roomId, User $user): PlazaRoom
    {
        $this->ensureNotSanctioned($user);

        $room = PlazaRoom::with('activeMembers')->findOrFail($roomId);

        if (! $room->is_active || $room->ended_at !== null) {
            throw ValidationException::withMessages([
                'room' => 'This Plaza space has already ended.',
            ]);
        }

        // Check if user is already an active member
        $existing = $room->activeMembers->firstWhere('user_id', $user->id);
        if ($existing) {
            return $room->load(['host', 'activeMembers.user', 'messages.user']);
        }

        // FR-31: Check capacity limitation (Max 8 participants)
        if ($room->activeMembers->count() >= $room->capacity) {
            throw ValidationException::withMessages([
                'room' => 'This space has reached its maximum capacity of '.$room->capacity.' participants.',
            ]);
        }

        PlazaRoomMember::create([
            'plaza_room_id' => $room->id,
            'user_id' => $user->id,
            'role' => 'listener',
            'is_muted' => false,
            'joined_at' => now(),
        ]);

        $room->increment('current_participants');

        broadcast(new PlazaRoomUpdated($room->id, 'member_joined', [
            'user' => [
                'id' => $user->id,
                'name' => $user->display_name ?? 'Stranger',
                'role' => 'listener',
            ],
            'current_participants' => $room->fresh()->current_participants,
        ]))->toOthers();

        return $room->load(['host', 'activeMembers.user', 'messages.user']);
    }

    /**
     * Leave a Plaza room.
     */
    public function leaveRoom(string $roomId, User $user): void
    {
        $room = PlazaRoom::findOrFail($roomId);

        $member = PlazaRoomMember::where('plaza_room_id', $room->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        if ($member) {
            $member->update(['left_at' => now()]);
            if ($room->current_participants > 0) {
                $room->decrement('current_participants');
            }

            broadcast(new PlazaRoomUpdated($room->id, 'member_left', [
                'userId' => $user->id,
                'current_participants' => $room->fresh()->current_participants,
            ]))->toOthers();

            // If the host left and nobody remains, end room
            if ($room->host_id === $user->id && $room->activeMembers()->count() === 0) {
                $room->update(['is_active' => false, 'ended_at' => now()]);
            }
        }
    }

    /**
     * Post a message in the group room with Sentinel moderation.
     */
    public function postMessage(string $roomId, User $user, string $body): PlazaMessage
    {
        $this->ensureNotSanctioned($user);

        // Sentinel safety filter
        $moderation = $this->sentinel->analyze($body);
        if ($moderation['flagged']) {
            throw ValidationException::withMessages([
                'body' => 'Message violates community safety guidelines: '.($moderation['flag_reason'] ?? 'Harmful content detected'),
            ]);
        }

        $message = PlazaMessage::create([
            'plaza_room_id' => $roomId,
            'user_id' => $user->id,
            'body' => $body,
        ]);

        broadcast(new PlazaMessageSent(
            roomId: $roomId,
            senderId: $user->id,
            senderName: $user->display_name ?? 'Stranger',
            body: $body,
            timestamp: now()->toIso8601String()
        ))->toOthers();

        return $message->load('user');
    }

    /**
     * Host action: mute or kick participant (FR-31 Host controls).
     */
    public function moderateMember(string $roomId, User $host, int $targetUserId, string $action): void
    {
        $room = PlazaRoom::findOrFail($roomId);

        if ($room->host_id !== $host->id) {
            throw ValidationException::withMessages([
                'host' => 'Only the room host can perform moderation actions.',
            ]);
        }

        $member = PlazaRoomMember::where('plaza_room_id', $room->id)
            ->where('user_id', $targetUserId)
            ->whereNull('left_at')
            ->firstOrFail();

        if ($action === 'mute') {
            $member->update(['is_muted' => true]);
            broadcast(new PlazaRoomUpdated($roomId, 'member_muted', ['userId' => $targetUserId]));
        } elseif ($action === 'unmute') {
            $member->update(['is_muted' => false]);
            broadcast(new PlazaRoomUpdated($roomId, 'member_unmuted', ['userId' => $targetUserId]));
        } elseif ($action === 'kick') {
            $member->update(['left_at' => now()]);
            $room->decrement('current_participants');
            broadcast(new PlazaRoomUpdated($roomId, 'member_kicked', ['userId' => $targetUserId]));
        }
    }

    /**
     * Get or create today's daily prompt and recent community responses.
     */
    public function getDailyPrompt(?User $user = null): array
    {
        $today = Carbon::today();

        $prompt = DailyPrompt::firstOrCreate(
            ['date' => $today],
            [
                'prompt' => $this->pickDailyPromptForDate($today),
                'responses_count' => 0,
            ]
        );

        $answers = DailyPromptAnswer::with('user')
            ->where('daily_prompt_id', $prompt->id)
            ->latest()
            ->take(15)
            ->get();

        $userAnswer = $user
            ? DailyPromptAnswer::where('daily_prompt_id', $prompt->id)->where('user_id', $user->id)->first()
            : null;

        return [
            'prompt' => $prompt,
            'answers' => $answers,
            'user_answer' => $userAnswer,
        ];
    }

    /**
     * Submit an answer to the daily prompt.
     */
    public function answerDailyPrompt(User $user, string $answer, int $moodScore = 3): DailyPromptAnswer
    {
        $this->ensureNotSanctioned($user);

        // Sentinel scan
        $analysis = $this->sentinel->analyze($answer);
        if ($analysis['flagged']) {
            throw ValidationException::withMessages([
                'answer' => 'Your response violates community safety rules: '.($analysis['flag_reason'] ?? 'Harmful content'),
            ]);
        }

        $dailyData = $this->getDailyPrompt($user);
        $prompt = $dailyData['prompt'];

        $existing = DailyPromptAnswer::where('daily_prompt_id', $prompt->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->update([
                'answer' => $answer,
                'mood_score' => $moodScore,
            ]);

            return $existing;
        }

        $response = DailyPromptAnswer::create([
            'daily_prompt_id' => $prompt->id,
            'user_id' => $user->id,
            'answer' => $answer,
            'mood_score' => $moodScore,
        ]);

        $prompt->increment('responses_count');

        $this->awardBadge($user, 'prompt-spark');

        return $response->load('user');
    }

    /**
     * Get upcoming events calendar.
     */
    public function getEventsCalendar(): Collection
    {
        $events = PlazaEvent::where('scheduled_at', '>=', now()->subHours(2))
            ->orderBy('scheduled_at')
            ->take(5)
            ->get();

        if ($events->isEmpty()) {
            $this->seedStarterEvents();

            return PlazaEvent::where('scheduled_at', '>=', now()->subHours(2))
                ->orderBy('scheduled_at')
                ->take(5)
                ->get();
        }

        return $events;
    }

    /**
     * RSVP to an event.
     */
    public function rsvpEvent(int $eventId): PlazaEvent
    {
        $event = PlazaEvent::findOrFail($eventId);
        $event->increment('attendees_count');

        return $event;
    }

    /**
     * Get all badges and user unlocked badges.
     */
    public function getBadgesCatalog(?User $user = null): array
    {
        $this->ensureBadgesSeeded();

        $allBadges = Badge::all();
        $unlockedIds = $user
            ? UserBadge::where('user_id', $user->id)->pluck('badge_id')->toArray()
            : [];

        return [
            'all' => $allBadges,
            'unlocked_ids' => $unlockedIds,
        ];
    }

    /**
     * Award a badge to a user.
     */
    public function awardBadge(User $user, string $slug): ?Badge
    {
        $this->ensureBadgesSeeded();

        $badge = Badge::where('slug', $slug)->first();
        if (! $badge) {
            return null;
        }

        UserBadge::firstOrCreate([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
        ], [
            'awarded_at' => now(),
        ]);

        return $badge;
    }

    protected function ensureNotSanctioned(User $user): void
    {
        $activeBan = Sanction::where('user_id', $user->id)
            ->whereIn('type', ['temp_ban', 'perm_ban'])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($activeBan) {
            throw ValidationException::withMessages([
                'user' => 'Your account is currently restricted from participating in Plaza spaces.',
            ]);
        }
    }

    protected function seedStarterRooms(): void
    {
        $starterRooms = [
            [
                'topic' => 'Lo-Fi Chill & Decompression Lounge',
                'vibe' => 'Chill',
                'description' => 'Unwind with soft music and cozy vibes. Say hello or just listen in.',
                'capacity' => 8,
            ],
            [
                'topic' => 'Midnight Thoughts & Judgment-Free Venting',
                'vibe' => 'Vent',
                'description' => 'Need to unload a stressful day? We are here to listen with open hearts.',
                'capacity' => 8,
            ],
            [
                'topic' => 'Night Owl Trivia & Lighthearted Bant',
                'vibe' => 'Play',
                'description' => 'Casual trivia duels and fun questions to take your mind off work.',
                'capacity' => 8,
            ],
            [
                'topic' => 'Deep Philosophy & Life Perspectives',
                'vibe' => 'Deep',
                'description' => 'Late night existential talks, dreams, universe curiosities, and calm talks.',
                'capacity' => 8,
            ],
        ];

        foreach ($starterRooms as $data) {
            PlazaRoom::create(array_merge($data, [
                'current_participants' => rand(2, 6),
                'is_active' => true,
                'starts_at' => now()->subMinutes(rand(10, 60)),
            ]));
        }
    }

    protected function seedStarterEvents(): void
    {
        $events = [
            [
                'title' => 'Midnight Guided Breathing & Sound Bath',
                'description' => 'A collective 15-minute relaxation exercise to calm anxiety and soothe the nervous system.',
                'scheduled_at' => now()->addHours(2),
                'vibe' => 'Chill',
                'host_name' => 'Emiringkay Wellness Bot',
                'attendees_count' => 14,
            ],
            [
                'title' => 'Open Mic Venting: Let It All Out',
                'description' => 'Speak for 2 minutes without interruptions, followed by warm affirmations from peers.',
                'scheduled_at' => now()->addDays(1)->setHour(20)->setMinute(0),
                'vibe' => 'Vent',
                'host_name' => 'Community Moderator',
                'attendees_count' => 28,
            ],
            [
                'title' => 'Weekend Community Trivia Duel Championship',
                'description' => 'Fun casual trivia showdown with real-time scoreboards and celebratory confetti.',
                'scheduled_at' => now()->addDays(2)->setHour(21)->setMinute(0),
                'vibe' => 'Play',
                'host_name' => 'Trivia Master',
                'attendees_count' => 42,
            ],
        ];

        foreach ($events as $event) {
            PlazaEvent::create($event);
        }
    }

    protected function ensureBadgesSeeded(): void
    {
        if (Badge::count() > 0) {
            return;
        }

        $badges = [
            [
                'slug' => 'prompt-spark',
                'name' => 'Daily Reflective',
                'description' => 'Answered the daily Plaza prompt with genuine honesty.',
                'icon' => 'sparkles',
                'category' => 'reflection',
            ],
            [
                'slug' => 'plaza-host',
                'name' => 'Circle Keeper',
                'description' => 'Hosted a welcoming Plaza group room for fellow night owls.',
                'icon' => 'home',
                'category' => 'community',
            ],
            [
                'slug' => 'night-owl',
                'name' => 'Starlight Wanderer',
                'description' => 'Spent mindful decompression time in the Soft Night Plaza.',
                'icon' => 'moon',
                'category' => 'karma',
            ],
            [
                'slug' => 'mindful-listener',
                'name' => 'Gentle Listener',
                'description' => 'Provided active listening and positive vibes to someone venting.',
                'icon' => 'heart',
                'category' => 'karma',
            ],
        ];

        foreach ($badges as $badge) {
            Badge::create($badge);
        }
    }

    protected function pickDailyPromptForDate(Carbon $date): string
    {
        $prompts = [
            'What is a tiny moment of comfort or peace you experienced today?',
            'What is one heavy burden you would like to set down tonight?',
            'Describe your favorite comfort food or drink and why it brings warmth to your soul.',
            'What is a song, sound, or voice that instantly settles your mind?',
            'If you could send a gentle reminder to your past self right now, what would you say?',
            'What made you smile today, even if just for a split second?',
            'What is a quiet joy you are looking forward to tomorrow?',
        ];

        $dayIndex = $date->dayOfYear % count($prompts);

        return $prompts[$dayIndex];
    }
}
