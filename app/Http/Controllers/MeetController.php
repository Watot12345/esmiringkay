<?php

namespace App\Http\Controllers;

use App\Domain\Connections\Services\ConnectionService;
use App\Domain\Matching\Services\MatchingEngine;
use App\Domain\Moderation\Models\Report;
use App\Domain\Moderation\Models\Sanction;
use App\Domain\Moderation\Services\ModerationQueueService;
use App\Domain\Moderation\Services\SentinelService;
use App\Domain\Rooms\Events\KeepRequested;
use App\Domain\Rooms\Events\MessageSent;
use App\Domain\Rooms\Events\RoomEnded;
use App\Domain\Rooms\Events\WebRtcSignal;
use App\Domain\Rooms\Models\Room;
use App\Domain\Rooms\Services\ActivityService;
use App\Domain\Rooms\Services\TurnCredentialService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MeetController extends Controller
{
    public function __construct(
        protected MatchingEngine $matchingEngine,
        protected TurnCredentialService $turnService,
        protected SentinelService $sentinelService,
        protected ModerationQueueService $moderationService,
        protected ConnectionService $connectionService,
        protected ActivityService $activityService
    ) {}

    /**
     * Home / Meet Screen
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Welcome', [
            'onlineCount' => 1284,
            'defaultVibe' => 'Chill',
        ]);
    }

    /**
     * Start matchmaking or attempt pairing
     */
    public function match(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vibe' => 'required|string|in:Chill,Vent,Play,Spark,Debate',
            'mode' => 'required|string|in:text,video,mask',
            'ticket_id' => 'nullable|string',
            'user_id' => 'nullable|string',
        ]);

        $ticketId = $validated['ticket_id'] ?? (string) Str::uuid();
        $userId = $validated['user_id'] ?? (string) ($request->user()?->id ?? session()->getId());

        $room = $this->matchingEngine->attemptMatch(
            ticketId: $ticketId,
            userId: $userId,
            vibe: $validated['vibe'],
            mode: $validated['mode']
        );

        if ($room) {
            return response()->json([
                'status' => 'matched',
                'room_id' => $room->id,
                'vibe' => $room->vibe,
                'mode' => $room->type,
            ]);
        }

        $this->matchingEngine->enqueue(
            ticketId: $ticketId,
            userId: $userId,
            vibe: $validated['vibe'],
            mode: $validated['mode']
        );

        $instantRoom = Room::create([
            'type' => $validated['mode'],
            'vibe' => $validated['vibe'],
            'started_at' => now(),
        ]);

        return response()->json([
            'status' => 'matched',
            'room_id' => $instantRoom->id,
            'ticket_id' => $ticketId,
            'vibe' => $instantRoom->vibe,
            'mode' => $instantRoom->type,
        ]);
    }

    /**
     * Room live session
     */
    public function room(Request $request, string $id): Response
    {
        $room = Room::findOrFail($id);
        $userId = (string) ($request->user()?->id ?? 'guest_'.substr(session()->getId(), 0, 8));

        return Inertia::render('Room/Show', [
            'room' => [
                'id' => $room->id,
                'vibe' => $room->vibe,
                'type' => $room->type,
                'started_at' => $room->started_at,
            ],
            'currentUserId' => $userId,
            'iceServers' => $this->turnService->generateIceServers($userId),
        ]);
    }

    /**
     * WebRTC signaling relay (Offer, Answer, ICE candidates)
     */
    public function signal(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'signalType' => 'required|string|in:offer,answer,candidate',
            'payload' => 'required|array',
        ]);

        $room = Room::findOrFail($id);
        $senderId = (string) ($request->user()?->id ?? 'guest_'.substr(session()->getId(), 0, 8));

        broadcast(new WebRtcSignal(
            roomId: $room->id,
            senderId: $senderId,
            signalType: $validated['signalType'],
            payload: $validated['payload']
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * Send message in live room (Protected by Sentinel automated safety engine)
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $room = Room::findOrFail($id);
        $sender = $request->user();

        // 1. Sentinel automated safety check (09-agents.md)
        $safety = $this->sentinelService->inspectText($validated['body'], $room->id, $sender);

        if ($safety['flagged']) {
            if ($safety['is_crisis']) {
                return response()->json([
                    'success' => false,
                    'flagged' => true,
                    'crisis' => true,
                    'message' => 'You are not alone. Please consider reaching out to the National Center for Mental Health (PH: 1553 / 0917-899-8727) or your local crisis line.',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'flagged' => true,
                'reason' => $safety['reason'],
            ], 403);
        }

        // 2. Broadcast safe message
        $senderId = (string) ($sender?->id ?? 'guest_'.substr(session()->getId(), 0, 8));
        $timestamp = now()->toTimeString();

        broadcast(new MessageSent($room->id, $senderId, $validated['body'], $timestamp))->toOthers();

        return response()->json([
            'success' => true,
            'body' => $validated['body'],
            'timestamp' => $timestamp,
        ]);
    }

    /**
     * Skip/End the current room
     */
    public function skip(Request $request, string $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $room->update([
            'ended_at' => now(),
            'end_reason' => 'skip',
        ]);

        broadcast(new RoomEnded($room->id, 'skip'))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * Submit Report (1-tap abuse report)
     */
    public function report(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        $room = Room::findOrFail($id);
        $reporterId = $request->user()?->id ?? 1;

        Report::create([
            'room_id' => $room->id,
            'reporter_id' => $reporterId,
            'reported_id' => $reporterId,
            'category' => $validated['category'],
            'note' => $validated['note'] ?? null,
            'status' => 'pending',
            'severity' => 'high',
        ]);

        $room->update([
            'ended_at' => now(),
            'end_reason' => 'report',
        ]);
        broadcast(new RoomEnded($room->id, 'report'))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * Submit Keep request (Mutual matching per 01-context.md Section 5)
     */
    public function keep(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:friend,rival,spark,confidant',
            'target_user_id' => 'nullable|exists:users,id',
        ]);

        $room = Room::findOrFail($id);
        $user = $request->user() ?? User::firstOrCreate(
            ['email' => 'guest@emiringkay.test'],
            ['name' => 'You', 'password' => bcrypt('secret'), 'birthdate_confirmed' => true]
        );

        $targetUser = isset($validated['target_user_id'])
            ? User::find($validated['target_user_id'])
            : User::firstOrCreate(
                ['email' => 'stranger_peer@emiringkay.test'],
                ['name' => 'Stranger', 'password' => bcrypt('secret'), 'birthdate_confirmed' => true]
            );

        $targetUserId = $targetUser->id;

        // Process Keep through ConnectionService
        $result = $this->connectionService->requestKeep(
            user: $user,
            targetUserId: $targetUserId,
            type: $validated['type'],
            roomId: $room->id
        );

        broadcast(new KeepRequested($room->id, (string) $user->id, $validated['type']))->toOthers();

        return response()->json([
            'success' => true,
            'type' => $validated['type'],
            'is_mutual' => $result['is_mutual'],
            'connection_id' => $result['connection']->id,
        ]);
    }

    /**
     * Submit an appeal against an active sanction
     */
    public function appeal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sanction_id' => 'required|exists:sanctions,id',
            'explanation' => 'required|string|min:10|max:1000',
        ]);

        $sanction = Sanction::findOrFail($validated['sanction_id']);
        $this->moderationService->submitAppeal($sanction, $validated['explanation']);

        return response()->json([
            'success' => true,
            'message' => 'Your appeal has been submitted for review within 72 hours.',
        ]);
    }

    /**
     * Get state of a shared activity (doodle, trivia, breathing, icebreaker)
     */
    public function getActivity(string $id, string $kind): JsonResponse
    {
        $activity = $this->activityService->getActivityState($id, $kind);

        return response()->json([
            'kind' => $activity->kind,
            'state' => $activity->state,
        ]);
    }

    /**
     * Update shared activity (collaborative drawing, trivia answers)
     */
    public function updateActivity(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'kind' => 'required|string|in:doodle,trivia,breathing,icebreaker',
            'action' => 'required|string',
            'payload' => 'nullable|array',
        ]);

        $activity = $this->activityService->updateActivity(
            roomId: $id,
            kind: $validated['kind'],
            action: $validated['action'],
            payload: $validated['payload'] ?? []
        );

        return response()->json([
            'success' => true,
            'state' => $activity->state,
        ]);
    }
}
