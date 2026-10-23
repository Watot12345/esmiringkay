<?php

namespace App\Domain\Connections\Services;

use App\Domain\Accounts\Services\TrustScoreService;
use App\Domain\Connections\Events\DirectMessageSent;
use App\Domain\Connections\Events\KeepMatched;
use App\Domain\Connections\Models\Connection;
use App\Domain\Connections\Models\DirectMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ConnectionService
{
    public function __construct(
        protected TrustScoreService $trustScoreService
    ) {}

    /**
     * Request a Keep from user to target user per 01-context.md Section 5.
     * Returns the connection and whether it resulted in a mutual match.
     *
     * @return array{connection: Connection, is_mutual: bool}
     */
    public function requestKeep(User $user, int $targetUserId, string $type, ?string $roomId = null): array
    {
        // 1. Check if the reverse request already exists (target user already chose Keep)
        $existingReverse = Connection::where('user_a', $targetUserId)
            ->where('user_b', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingReverse) {
            // MUTUAL KEEP!
            $finalType = $this->resolveMutualType($existingReverse->type, $type);
            $existingReverse->update([
                'type' => $finalType,
                'status' => 'mutual',
            ]);

            // Reward both users with trust points for positive encounter per PRD
            $this->trustScoreService->reward($user, 3, 'Mutual Keep formed');
            $targetUser = User::find($targetUserId);
            if ($targetUser) {
                $this->trustScoreService->reward($targetUser, 3, 'Mutual Keep formed');
            }

            broadcast(new KeepMatched($existingReverse->id, $user->id, $targetUserId, $finalType))->toOthers();

            return [
                'connection' => $existingReverse,
                'is_mutual' => true,
            ];
        }

        // 2. Otherwise create or update own pending request
        $connection = Connection::updateOrCreate(
            ['user_a' => $user->id, 'user_b' => $targetUserId],
            ['type' => $type, 'status' => 'pending']
        );

        return [
            'connection' => $connection,
            'is_mutual' => false,
        ];
    }

    /**
     * Get all active mutual connections for user's Circle per 06-ui-ux-structure.md.
     */
    public function getCircleConnections(User $user): Collection
    {
        return Connection::with(['userA.profile', 'userB.profile', 'directMessages' => function ($q) {
            $q->latest()->limit(1);
        }])
            ->where(function ($query) use ($user) {
                $query->where('user_a', $user->id)
                    ->orWhere('user_b', $user->id);
            })
            ->where('status', 'mutual')
            ->latest('updated_at')
            ->get();
    }

    /**
     * Send direct message between mutual connections.
     */
    public function sendDirectMessage(User $sender, int $connectionId, string $body): DirectMessage
    {
        $connection = Connection::where('id', $connectionId)
            ->where('status', 'mutual')
            ->where(function ($q) use ($sender) {
                $q->where('user_a', $sender->id)
                    ->orWhere('user_b', $sender->id);
            })
            ->firstOrFail();

        $message = DirectMessage::create([
            'connection_id' => $connection->id,
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        $recipientId = ($connection->user_a === $sender->id) ? $connection->user_b : $connection->user_a;

        broadcast(new DirectMessageSent($connection->id, $sender->id, $recipientId, $body))->toOthers();

        return $message;
    }

    /**
     * Either side can end a connection instantly with zero penalties per 01-context.md.
     */
    public function endConnection(User $user, int $connectionId): void
    {
        $connection = Connection::where('id', $connectionId)
            ->where(function ($q) use ($user) {
                $q->where('user_a', $user->id)
                    ->orWhere('user_b', $user->id);
            })
            ->firstOrFail();

        $connection->update(['status' => 'ended']);
    }

    /**
     * If relationship types differ, prefer mutual consent safety (e.g., Spark requires both sides).
     */
    protected function resolveMutualType(string $typeA, string $typeB): string
    {
        if ($typeA === $typeB) {
            return $typeA;
        }

        // Spark requires mutual opt-in; otherwise fallback to Friend
        if ($typeA === Connection::TYPE_SPARK || $typeB === Connection::TYPE_SPARK) {
            return Connection::TYPE_FRIEND;
        }

        return $typeB;
    }
}
