<?php

namespace App\Domain\Matching\Services;

use App\Domain\Rooms\Models\Room;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class MatchingEngine
{
    public const SEVEN_DAYS_SECONDS = 7 * 24 * 60 * 60;

    /**
     * Join the matching queue with vibe, mode, language, and trust pool.
     */
    public function enqueue(
        string $ticketId,
        string $userId,
        string $vibe = 'Chill',
        string $mode = 'text',
        string $lang = 'en',
        string $pool = 'standard'
    ): void {
        $this->heartbeat($ticketId);
        $key = $this->getQueueKey($pool, $vibe, $mode, $lang);
        $ticketPayload = json_encode([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'vibe' => $vibe,
            'mode' => $mode,
            'lang' => $lang,
            'joined_at' => microtime(true),
        ]);

        // Add to sorted set with timestamp as score
        try {
            Redis::zadd($key, microtime(true), $ticketPayload);
        } catch (\Throwable $e) {
            // Fallback to cache array if Redis extension or service is connecting locally
            $existing = Cache::get($key, []);
            $existing[$ticketId] = [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'vibe' => $vibe,
                'mode' => $mode,
                'lang' => $lang,
                'joined_at' => microtime(true),
            ];
            Cache::put($key, $existing, 300);
        }
    }

    /**
     * Atomically try to pair, otherwise join the queue. Running both steps under one lock
     * prevents two simultaneous users from each missing the other and waiting forever.
     */
    public function matchOrEnqueue(
        string $ticketId,
        string $userId,
        string $vibe = 'Chill',
        string $mode = 'text',
        string $lang = 'en',
        string $pool = 'standard'
    ): ?Room {
        $lockKey = "match_lock:{$pool}:{$vibe}:{$mode}";

        try {
            return Cache::lock($lockKey, 5)->block(5, function () use ($ticketId, $userId, $vibe, $mode, $lang, $pool) {
                $room = $this->findAndPair($ticketId, $userId, $vibe, $mode, $lang, $pool);

                if (! $room) {
                    $this->enqueue($ticketId, $userId, $vibe, $mode, $lang, $pool);
                }

                return $room;
            });
        } catch (LockTimeoutException $e) {
            $this->enqueue($ticketId, $userId, $vibe, $mode, $lang, $pool);

            return null;
        }
    }

    /**
     * Keep a waiting ticket alive; tickets not polled for ~15s are treated as abandoned.
     */
    public function heartbeat(string $ticketId): void
    {
        Cache::put("ticket_alive:{$ticketId}", true, 15);
    }

    /**
     * Attempt to pair the given ticket with a compatible waiting ticket.
     * Uses atomic locking and checks 7-day skip/block exclusions (FR-12).
     */
    public function attemptMatch(
        string $ticketId,
        string $userId,
        string $vibe = 'Chill',
        string $mode = 'text',
        string $lang = 'en',
        string $pool = 'standard'
    ): ?Room {
        $lockKey = "match_lock:{$pool}:{$vibe}:{$mode}";

        try {
            return Cache::lock($lockKey, 5)->block(5, fn () => $this->findAndPair($ticketId, $userId, $vibe, $mode, $lang, $pool));
        } catch (LockTimeoutException $e) {
            return null;
        }
    }

    protected function findAndPair(
        string $ticketId,
        string $userId,
        string $vibe,
        string $mode,
        string $lang,
        string $pool
    ): ?Room {
        $key = $this->getQueueKey($pool, $vibe, $mode, $lang);
        $candidates = [];

        try {
            // Fetch up to 10 waiting candidates from sorted set
            $rawCandidates = Redis::zrange($key, 0, 9);
            foreach ($rawCandidates as $raw) {
                $candidates[] = json_decode($raw, true);
            }
        } catch (\Throwable $e) {
            $candidates = array_values(Cache::get($key, []));
        }

        foreach ($candidates as $candidate) {
            // Cannot pair with self
            if ($candidate['ticket_id'] === $ticketId || $candidate['user_id'] === $userId) {
                continue;
            }

            // Skip abandoned tickets (tab closed / cancelled): clients heartbeat while waiting
            if (! Cache::has("ticket_alive:{$candidate['ticket_id']}")) {
                $this->removeCandidate($key, $candidate);

                continue;
            }

            // Check 7-day exclusion list (skips and blocks per FR-12)
            if ($this->isExcluded($userId, $candidate['user_id'])) {
                continue;
            }

            // Compatible pair found! Remove both from queue
            $this->removeCandidate($key, $candidate);
            $this->removeTicket($key, $ticketId);

            // Create live Room for both peers
            $room = Room::create([
                'type' => $mode,
                'vibe' => $vibe,
                'started_at' => now(),
            ]);

            // Save match for the waiting user's ticket
            Cache::put("ticket_match:{$candidate['ticket_id']}", [
                'room_id' => $room->id,
                'vibe' => $vibe,
                'mode' => $mode,
            ], 120);

            return $room;
        }

        return null;
    }

    /**
     * Check if a waiting ticket has been paired with a live room.
     */
    public function getTicketMatch(string $ticketId): ?array
    {
        return Cache::get("ticket_match:{$ticketId}");
    }

    /**
     * Remove ticket from queue when cancelled or matched.
     */
    public function removeTicket(string $key, string $ticketId): void
    {
        try {
            $rawCandidates = Redis::zrange($key, 0, -1);
            foreach ($rawCandidates as $raw) {
                $data = json_decode($raw, true);
                if ($data && ($data['ticket_id'] ?? null) === $ticketId) {
                    Redis::zrem($key, $raw);
                }
            }
        } catch (\Throwable $e) {
            $existing = Cache::get($key, []);
            unset($existing[$ticketId]);
            Cache::put($key, $existing, 300);
        }
    }

    protected function removeCandidate(string $key, array $candidate): void
    {
        try {
            Redis::zrem($key, json_encode($candidate));
        } catch (\Throwable $e) {
            $existing = Cache::get($key, []);
            unset($existing[$candidate['ticket_id']]);
            Cache::put($key, $existing, 300);
        }
    }

    /**
     * Record a skip between two users (7 days exclusion per FR-12).
     */
    public function recordSkip(string $userId, string $targetUserId): void
    {
        Cache::put("exclusion:{$userId}:{$targetUserId}", true, self::SEVEN_DAYS_SECONDS);
        Cache::put("exclusion:{$targetUserId}:{$userId}", true, self::SEVEN_DAYS_SECONDS);
    }

    /**
     * Record a block between two users.
     */
    public function recordBlock(string $userId, string $targetUserId): void
    {
        Cache::forever("blocked:{$userId}:{$targetUserId}", true);
        Cache::forever("blocked:{$targetUserId}:{$userId}", true);
    }

    /**
     * Check if two users are currently excluded from rematching.
     */
    public function isExcluded(string $userA, string $userB): bool
    {
        if (Cache::has("blocked:{$userA}:{$userB}") || Cache::has("blocked:{$userB}:{$userA}")) {
            return true;
        }

        if (Cache::has("exclusion:{$userA}:{$userB}") || Cache::has("exclusion:{$userB}:{$userA}")) {
            return true;
        }

        return false;
    }

    public function getQueueKey(string $pool, string $vibe, string $mode, string $lang): string
    {
        return "queue:{$pool}:{$vibe}:{$mode}:{$lang}";
    }
}
