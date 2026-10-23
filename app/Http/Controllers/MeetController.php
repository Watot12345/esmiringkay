<?php

namespace App\Http\Controllers;

use App\Domain\Rooms\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeetController extends Controller
{
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
     * Start matchmaking queue
     */
    public function match(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vibe' => 'required|string|in:Chill,Vent,Play,Spark,Debate',
            'mode' => 'required|string|in:text,video,mask',
        ]);

        // Create or find a matching room
        $room = Room::create([
            'type' => $validated['mode'],
            'vibe' => $validated['vibe'],
            'started_at' => now(),
        ]);

        return response()->json([
            'status' => 'matched',
            'room_id' => $room->id,
            'vibe' => $room->vibe,
            'mode' => $room->type,
        ]);
    }

    /**
     * Room live session
     */
    public function room(string $id): Response
    {
        $room = Room::findOrFail($id);

        return Inertia::render('Room/Show', [
            'room' => [
                'id' => $room->id,
                'vibe' => $room->vibe,
                'type' => $room->type,
                'started_at' => $room->started_at,
            ],
        ]);
    }
}
