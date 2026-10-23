<?php

namespace App\Http\Controllers;

use App\Domain\Matching\Services\MatchingEngine;
use App\Domain\Moderation\Models\Report;
use App\Domain\Rooms\Events\KeepRequested;
use App\Domain\Rooms\Events\MessageSent;
use App\Domain\Rooms\Events\RoomEnded;
use App\Domain\Rooms\Events\WebRtcSignal;
use App\Domain\Rooms\Models\Room;
use App\Domain\Rooms\Services\TurnCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MeetController extends Controller
{
    public function __construct(
        protected MatchingEngine $matchingEngine,
        protected TurnCredentialService $turnService
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

        // Attempt instant pair with waiting candidate
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

        // Otherwise enqueue
        $this->matchingEngine->enqueue(
            ticketId: $ticketId,
            userId: $userId,
            vibe: $validated['vibe'],
            mode: $validated['mode']
        );

        // Immediate room creation for test/demo
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
     * WebRTC signaling relay (SDP Offer, SDP Answer, ICE candidates)
     * Relayed directly via Reverb websocket event, never persisted per 01-context.md
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
     * Send message in live room
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $room = Room::findOrFail($id);
        $senderId = (string) ($request->user()?->id ?? 'guest_'.substr(session()->getId(), 0, 8));
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
     * Submit Keep request
     */
    public function keep(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:friend,rival,spark,confidant',
        ]);

        $room = Room::findOrFail($id);
        $senderId = (string) ($request->user()?->id ?? 'guest_'.substr(session()->getId(), 0, 8));

        broadcast(new KeepRequested($room->id, $senderId, $validated['type']))->toOthers();

        return response()->json([
            'success' => true,
            'type' => $validated['type'],
        ]);
    }
}
