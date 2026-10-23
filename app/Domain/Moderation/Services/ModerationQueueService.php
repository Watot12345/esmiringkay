<?php

namespace App\Domain\Moderation\Services;

use App\Domain\Accounts\Services\TrustScoreService;
use App\Domain\Moderation\Models\Report;
use App\Domain\Moderation\Models\Sanction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ModerationQueueService
{
    public function __construct(
        protected TrustScoreService $trustScoreService
    ) {}

    /**
     * Get pending report queue sorted by severity and recency per 08-workflow.md Section 5.
     */
    public function getPendingQueue(int $limit = 20): Collection
    {
        return Report::with(['reporter', 'reported', 'room'])
            ->where('status', 'pending')
            ->orderByRaw("CASE severity 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 END")
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Dismiss a false or ungrounded report.
     */
    public function dismiss(Report $report, ?string $note = null): void
    {
        $report->update([
            'status' => 'dismissed',
            'note' => $note ? "Dismissed: {$note}" : $report->note,
        ]);
    }

    /**
     * Apply a sanction to the reported user per FR-43.
     */
    public function applySanction(
        Report $report,
        string $sanctionType,
        string $reason,
        ?int $durationDays = null
    ): Sanction {
        $reportedUser = $report->reported;

        $expiresAt = $durationDays ? now()->addDays($durationDays) : null;

        $sanction = Sanction::create([
            'user_id' => $reportedUser->id,
            'type' => $sanctionType,
            'reason' => $reason,
            'expires_at' => $expiresAt,
            'appeal_status' => null,
        ]);

        // Adjust user status & trust score based on sanction type
        switch ($sanctionType) {
            case Sanction::TYPE_WARN:
                $this->trustScoreService->penalize($reportedUser, 10, "Warning: {$reason}");
                break;
            case Sanction::TYPE_MUTE:
                $reportedUser->update(['status' => 'muted']);
                $this->trustScoreService->penalize($reportedUser, 20, "Mute: {$reason}");
                break;
            case Sanction::TYPE_TEMP_BAN:
                $reportedUser->update(['status' => 'suspended']);
                $this->trustScoreService->penalize($reportedUser, 35, "Temp ban: {$reason}");
                break;
            case Sanction::TYPE_PERM_BAN:
            case Sanction::TYPE_DEVICE_BAN:
                $reportedUser->update(['status' => 'banned']);
                $this->trustScoreService->penalize($reportedUser, 100, "Permanent ban: {$reason}");
                break;
        }

        $report->update(['status' => 'sanctioned']);

        return $sanction;
    }

    /**
     * User submits an appeal against an active sanction.
     */
    public function submitAppeal(Sanction $sanction, string $explanation): void
    {
        $sanction->update([
            'appeal_status' => 'pending',
            'reason' => "{$sanction->reason} | Appeal note: {$explanation}",
        ]);
    }

    /**
     * Moderator decides on an appeal within 72h window per 08-workflow.md Section 5.
     */
    public function resolveAppeal(Sanction $sanction, bool $approved, string $reviewerNote): void
    {
        if ($approved) {
            $sanction->update([
                'appeal_status' => 'approved',
                'expires_at' => now(), // expire immediately
            ]);
            $sanction->user->update(['status' => 'active']);
            $this->trustScoreService->reward($sanction->user, 15, 'Appeal approved');
        } else {
            $sanction->update([
                'appeal_status' => 'rejected',
                'reason' => "{$sanction->reason} | Appeal rejected: {$reviewerNote}",
            ]);
        }
    }
}
