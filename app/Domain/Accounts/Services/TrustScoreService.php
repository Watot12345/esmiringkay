<?php

namespace App\Domain\Accounts\Services;

use App\Models\User;

class TrustScoreService
{
    public const MIN_SCORE = 0;

    public const MAX_SCORE = 100;

    public const DEFAULT_SCORE = 50;

    /**
     * Determine user's trust tier pool (new, standard, trusted, restricted) per FR-13.
     */
    public function getTrustTier(User $user): string
    {
        $score = $user->trust_score ?? self::DEFAULT_SCORE;

        if ($user->status === 'banned' || $score < 20) {
            return 'restricted';
        }

        if ($score >= 75) {
            return 'trusted';
        }

        if ($user->created_at && $user->created_at->diffInHours(now()) < 24) {
            return 'new';
        }

        return 'standard';
    }

    /**
     * Award trust points for positive social interactions (e.g., mutual Keeps, good mood scores).
     */
    public function reward(User $user, int $points, string $reason): void
    {
        $newScore = min(self::MAX_SCORE, ($user->trust_score ?? self::DEFAULT_SCORE) + $points);
        $user->update(['trust_score' => $newScore]);
    }

    /**
     * Deduct trust points for violations, skips, or user reports.
     */
    public function penalize(User $user, int $points, string $reason): void
    {
        $current = $user->trust_score ?? self::DEFAULT_SCORE;
        $newScore = max(self::MIN_SCORE, $current - $points);

        $updates = ['trust_score' => $newScore];

        // If score falls into critical threshold, mute or restrict account
        if ($newScore < 20 && $user->status === 'active') {
            $updates['status'] = 'restricted';
        }

        $user->update($updates);
    }
}
