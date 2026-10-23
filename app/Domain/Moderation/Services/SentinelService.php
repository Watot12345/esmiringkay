<?php

namespace App\Domain\Moderation\Services;

use App\Domain\Accounts\Services\TrustScoreService;
use App\Domain\Moderation\Models\Report;
use App\Domain\Rooms\Events\RoomEnded;
use App\Domain\Rooms\Models\Room;
use App\Models\User;

class SentinelService
{
    // Banned patterns and categories for real-time text analysis
    protected array $severePatterns = [
        'violence' => ['/kill yourself/i', '/i will kill you/i', '/commit suicide/i'],
        'child_safety' => ['/underage/i', '/csam/i'],
        'harassment' => ['/\b(nigger|faggot|kike|chink)\b/i'],
        'commercial_spam' => ['/t\.me\//i', '/wa\.me\//i', '/cashapp/i', '/venmo/i'],
    ];

    protected array $crisisPatterns = [
        '/\b(want to die|suicide|end my life|kill myself)\b/i',
    ];

    public function __construct(
        protected TrustScoreService $trustScoreService
    ) {}

    /**
     * Inspect text message in real-time per 09-agents.md Sentinel role.
     *
     * @return array{flagged: bool, action: string, category: ?string, reason: ?string, is_crisis: bool}
     */
    public function inspectText(string $body, string $roomId, ?User $sender = null): array
    {
        // 1. Check for crisis / self-harm intent (09-agents.md Calm Coach & Sentinel)
        foreach ($this->crisisPatterns as $pattern) {
            if (preg_match($pattern, $body)) {
                return [
                    'flagged' => true,
                    'action' => 'crisis_help',
                    'category' => 'crisis',
                    'reason' => 'User may need immediate emotional support or helpline resources.',
                    'is_crisis' => true,
                ];
            }
        }

        // 2. Check for severe abuse cases
        foreach ($this->severePatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $body)) {
                    $this->triggerSevereAction($roomId, $category, $body, $sender);

                    return [
                        'flagged' => true,
                        'action' => 'end_room',
                        'category' => $category,
                        'reason' => "Message violated platform safety guidelines ({$category}).",
                        'is_crisis' => false,
                    ];
                }
            }
        }

        return [
            'flagged' => false,
            'action' => 'allow',
            'category' => null,
            'reason' => null,
            'is_crisis' => false,
        ];
    }

    /**
     * Trigger severe safety enforcement: end room immediately, reduce trust score, file priority report.
     */
    protected function triggerSevereAction(string $roomId, string $category, string $evidenceText, ?User $sender): void
    {
        $room = Room::find($roomId);
        if ($room && is_null($room->ended_at)) {
            $room->update([
                'ended_at' => now(),
                'end_reason' => 'sentinel_flag',
            ]);
            broadcast(new RoomEnded($room->id, 'sentinel_flag'))->toOthers();
        }

        if ($sender) {
            // Deduct trust score
            $this->trustScoreService->penalize($sender, 25, "Sentinel flag: {$category}");

            // Create high-severity report for moderator queue
            Report::create([
                'room_id' => $roomId,
                'reporter_id' => $sender->id, // Automated Sentinel system
                'reported_id' => $sender->id,
                'category' => "Sentinel: {$category}",
                'note' => 'Automated flag on text snippet: '.substr($evidenceText, 0, 100),
                'status' => 'pending',
                'severity' => 'critical',
            ]);
        }
    }
}
