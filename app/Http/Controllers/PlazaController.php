<?php

namespace App\Http\Controllers;

use App\Domain\Plaza\Services\PlazaService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlazaController extends Controller
{
    public function __construct(
        protected PlazaService $plazaService
    ) {}

    /**
     * Plaza Overview: Group spaces, daily prompt, events calendar, and badges.
     */
    public function index(Request $request): Response
    {
        $user = $this->resolveUser($request);

        $rooms = $this->plazaService->getActiveRooms();
        $dailyData = $this->plazaService->getDailyPrompt($user);
        $events = $this->plazaService->getEventsCalendar();
        $badges = $this->plazaService->getBadgesCatalog($user);

        return Inertia::render('Plaza/Index', [
            'rooms' => $rooms,
            'dailyPrompt' => [
                'id' => $dailyData['prompt']->id,
                'prompt' => $dailyData['prompt']->prompt,
                'responses_count' => $dailyData['prompt']->responses_count,
                'answers' => $dailyData['answers']->map(fn ($a) => [
                    'id' => $a->id,
                    'answer' => $a->answer,
                    'mood_score' => $a->mood_score,
                    'author' => $a->user?->display_name ?? 'Stranger',
                    'created_at' => $a->created_at->diffForHumans(),
                ]),
                'user_answered' => $dailyData['user_answer'] !== null,
                'user_answer' => $dailyData['user_answer'] ? [
                    'answer' => $dailyData['user_answer']->answer,
                    'mood_score' => $dailyData['user_answer']->mood_score,
                ] : null,
            ],
            'events' => $events->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'description' => $e->description,
                'scheduled_at' => $e->scheduled_at->format('M d, H:i'),
                'time_until' => $e->scheduled_at->diffForHumans(),
                'vibe' => $e->vibe,
                'host_name' => $e->host_name,
                'attendees_count' => $e->attendees_count,
            ]),
            'badges' => [
                'all' => $badges['all'],
                'unlocked_ids' => $badges['unlocked_ids'],
            ],
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->display_name ?? $user->name,
            ],
        ]);
    }

    /**
     * Create a new Plaza room (host).
     */
    public function createRoom(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'topic' => 'required|string|max:100',
            'vibe' => 'nullable|string|in:Chill,Vent,Play,Deep,Spark',
            'description' => 'nullable|string|max:300',
            'capacity' => 'nullable|integer|min:2|max:8',
        ]);

        $room = $this->plazaService->createRoom($user, $validated);

        return response()->json([
            'success' => true,
            'room' => $room,
            'redirect_url' => "/plaza/rooms/{$room->id}",
        ]);
    }

    /**
     * Show live Plaza group space with up to 8 participant slots (FR-31).
     */
    public function show(Request $request, string $id): Response
    {
        $user = $this->resolveUser($request);

        // Auto join or load
        $room = $this->plazaService->joinRoom($id, $user);

        $isHost = $room->host_id === $user->id;
        $memberRecord = $room->activeMembers->firstWhere('user_id', $user->id);

        return Inertia::render('Plaza/Show', [
            'room' => [
                'id' => $room->id,
                'topic' => $room->topic,
                'vibe' => $room->vibe,
                'description' => $room->description,
                'capacity' => $room->capacity,
                'current_participants' => $room->current_participants,
                'host_id' => $room->host_id,
                'members' => $room->activeMembers->map(fn ($m) => [
                    'id' => $m->user_id,
                    'name' => $m->user?->display_name ?? 'Stranger',
                    'role' => $m->role,
                    'is_muted' => $m->is_muted,
                    'is_self' => $m->user_id === $user->id,
                    'is_host' => $m->user_id === $room->host_id,
                ]),
                'messages' => $room->messages()->with('user')->latest()->take(30)->get()->reverse()->values()->map(fn ($msg) => [
                    'id' => $msg->id,
                    'sender_id' => $msg->user_id,
                    'sender_name' => $msg->user?->display_name ?? 'Stranger',
                    'body' => $msg->body,
                    'time' => $msg->created_at->format('H:i'),
                    'is_me' => $msg->user_id === $user->id,
                ]),
            ],
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->display_name ?? $user->name,
                'isHost' => $isHost,
                'role' => $memberRecord?->role ?? 'listener',
            ],
        ]);
    }

    /**
     * Send message in Plaza room chat.
     */
    public function message(Request $request, string $id): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'body' => 'required|string|max:500',
        ]);

        $msg = $this->plazaService->postMessage($id, $user, $validated['body']);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $msg->id,
                'sender_id' => $user->id,
                'sender_name' => $user->display_name ?? 'Stranger',
                'body' => $msg->body,
                'time' => $msg->created_at->format('H:i'),
                'is_me' => true,
            ],
        ]);
    }

    /**
     * Leave a group space.
     */
    public function leave(Request $request, string $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        $this->plazaService->leaveRoom($id, $user);

        return response()->json(['success' => true]);
    }

    /**
     * Host moderation action (kick, mute, unmute).
     */
    public function moderate(Request $request, string $id): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'target_user_id' => 'required|integer',
            'action' => 'required|string|in:mute,unmute,kick',
        ]);

        $this->plazaService->moderateMember($id, $user, $validated['target_user_id'], $validated['action']);

        return response()->json(['success' => true]);
    }

    /**
     * Submit an answer to the daily prompt.
     */
    public function answerDailyPrompt(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'answer' => 'required|string|max:280',
            'mood_score' => 'nullable|integer|min:1|max:5',
        ]);

        $answer = $this->plazaService->answerDailyPrompt($user, $validated['answer'], $validated['mood_score'] ?? 3);

        return response()->json([
            'success' => true,
            'answer' => [
                'id' => $answer->id,
                'answer' => $answer->answer,
                'mood_score' => $answer->mood_score,
                'author' => $user->display_name ?? 'Stranger',
                'created_at' => 'Just now',
            ],
        ]);
    }

    /**
     * RSVP to an upcoming community event.
     */
    public function rsvpEvent(Request $request, int $id): JsonResponse
    {
        $event = $this->plazaService->rsvpEvent($id);

        return response()->json([
            'success' => true,
            'attendees_count' => $event->attendees_count,
        ]);
    }

    protected function resolveUser(Request $request): User
    {
        return $request->user() ?? User::firstOrCreate(
            ['email' => 'guest@emiringkay.test'],
            [
                'name' => 'You',
                'display_name' => 'Night Wanderer',
                'password' => bcrypt('secret'),
                'birthdate_confirmed' => true,
                'trust_score' => 65,
                'status' => 'active',
            ]
        );
    }
}
