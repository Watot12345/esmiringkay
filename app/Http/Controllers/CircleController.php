<?php

namespace App\Http\Controllers;

use App\Domain\Connections\Models\Connection;
use App\Domain\Connections\Services\ConnectionService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CircleController extends Controller
{
    public function __construct(
        protected ConnectionService $connectionService
    ) {}

    /**
     * Circle Home: Displays active Keeps (Friends, Rivals, Sparks, Confidants)
     */
    public function index(Request $request): Response
    {
        // For testing/guest demo, fallback to first user or create one
        $user = $request->user() ?? User::firstOrCreate(
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

        $connections = $this->connectionService->getCircleConnections($user);

        $formatted = $connections->map(function ($conn) use ($user) {
            $partner = ($conn->user_a === $user->id) ? $conn->userB : $conn->userA;
            $lastMsg = $conn->directMessages->first();

            return [
                'id' => $conn->id,
                'type' => $conn->type,
                'partner' => [
                    'id' => $partner->id,
                    'name' => $partner->display_name ?? $partner->name,
                    'avatar' => $partner->profile?->avatar,
                ],
                'last_message' => $lastMsg ? [
                    'body' => $lastMsg->body,
                    'time' => $lastMsg->created_at->diffForHumans(),
                    'is_me' => $lastMsg->sender_id === $user->id,
                ] : null,
                'updated_at' => $conn->updated_at,
                'is_online' => true, // Demo presence
            ];
        });

        return Inertia::render('Circle/Index', [
            'connections' => $formatted,
        ]);
    }

    /**
     * View 1:1 direct conversation with a mutual Keep
     */
    public function show(Request $request, int $id): Response
    {
        $user = $request->user() ?? User::first();

        $connection = Connection::with(['userA.profile', 'userB.profile', 'directMessages'])
            ->where('id', $id)
            ->where('status', 'mutual')
            ->where(function ($q) use ($user) {
                $q->where('user_a', $user->id)
                    ->orWhere('user_b', $user->id);
            })
            ->firstOrFail();

        $partner = ($connection->user_a === $user->id) ? $connection->userB : $connection->userA;

        $messages = $connection->directMessages->map(function ($dm) use ($user) {
            return [
                'id' => $dm->id,
                'body' => $dm->body,
                'sender_id' => $dm->sender_id,
                'is_me' => $dm->sender_id === $user->id,
                'time' => $dm->created_at->format('H:i'),
            ];
        });

        return Inertia::render('Circle/Show', [
            'connection' => [
                'id' => $connection->id,
                'type' => $connection->type,
                'partner' => [
                    'id' => $partner->id,
                    'name' => $partner->display_name ?? $partner->name,
                    'avatar' => $partner->profile?->avatar,
                ],
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Send direct message in Circle
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $user = $request->user() ?? User::first();
        $message = $this->connectionService->sendDirectMessage($user, $id, $validated['body']);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'is_me' => true,
                'time' => now()->format('H:i'),
            ],
        ]);
    }

    /**
     * End connection
     */
    public function end(Request $request, int $id): JsonResponse
    {
        $user = $request->user() ?? User::first();
        $this->connectionService->endConnection($user, $id);

        return response()->json(['success' => true]);
    }
}
