<?php

namespace AlertMetrics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EngagementScorer
{
    /**
     * Calculate engagement score for a subscriber.
     *
     * The engagement score determines how actively a subscriber interacts
     * with alerts. Higher scores mean the subscriber is more engaged and
     * should receive higher-priority routing.
     *
     * Supports two contexts:
     * - "realtime": Based on recent interaction patterns (clicks, opens, responses)
     * - "digest": Based on digest-specific metrics (open rate, action rate)
     *
     * @param  int     $subscriberId
     * @param  string  $context  Either "realtime" or "digest"
     * @return float   Score between 0.0 and 1.0
     */
    public function calculateScore(int $subscriberId, string $context = 'realtime'): float
    {
        $cacheKey = "engagement::{$subscriberId}::{$context}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (float) $cached;
        }

        if ($context === 'digest') {
            $score = $this->calculateDigestEngagement($subscriberId);
        } else {
            $score = $this->calculateRealtimeEngagement($subscriberId);
        }

        // Clamp between 0 and 1
        $score = max(0.0, min(1.0, $score));

        Cache::put($cacheKey, $score, 3600);

        Log::debug('EngagementScorer: Calculated score', [
            'subscriber_id' => $subscriberId,
            'context' => $context,
            'score' => $score,
        ]);

        return $score;
    }

    /**
     * Calculate realtime engagement based on recent activity.
     *
     * Factors:
     * - Notification frequency (more = higher base)
     * - Recency of last notification (recent = bonus)
     * - Response rate estimation
     */
    protected function calculateRealtimeEngagement(int $subscriberId): float
    {
        $subscriber = \App\Models\Subscriber::find($subscriberId);

        if (!$subscriber) {
            return 0.0;
        }

        $totalNotifications = $subscriber->notification_count ?? 0;
        $lastNotified = $subscriber->last_notified_at;

        // Base score from notification count (logarithmic scale)
        $baseScore = $totalNotifications > 0
            ? min(0.5, log($totalNotifications + 1, 10) / 4)
            : 0.0;

        // Recency bonus (higher if notified recently)
        $recencyBonus = 0.0;
        if ($lastNotified) {
            $daysSince = now()->diffInDays($lastNotified);
            $recencyBonus = max(0.0, 0.3 - ($daysSince * 0.02));
        }

        // Activity bonus based on recent 7-day volume
        $recentCount = \App\Models\Notification::where('subscriber_id', $subscriberId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $activityBonus = min(0.2, $recentCount * 0.02);

        return $baseScore + $recencyBonus + $activityBonus;
    }

    /**
     * Calculate digest-specific engagement.
     *
     * Uses different signals optimized for batch/digest context:
     * - Historical digest interaction rate
     * - Preferred notification times
     * - Digest-specific response patterns
     */
    protected function calculateDigestEngagement(int $subscriberId): float
    {
        $subscriber = \App\Models\Subscriber::find($subscriberId);

        if (!$subscriber) {
            return 0.0;
        }

        $totalNotifications = $subscriber->notification_count ?? 0;

        // Digest engagement uses a different formula
        // focused on batch interaction patterns
        $baseScore = $totalNotifications > 0
            ? min(0.4, log($totalNotifications + 1, 10) / 5)
            : 0.0;

        // Digest subscribers who receive many alerts tend to be less engaged
        // per individual alert (attention dilution)
        $recentCount = \App\Models\Notification::where('subscriber_id', $subscriberId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $dilutionFactor = $recentCount > 50 ? 0.8 : 1.0;

        // Consistency bonus — subscribers who regularly appear get higher scores
        $weeklyPresence = \App\Models\Notification::where('subscriber_id', $subscriberId)
            ->where('created_at', '>=', now()->subDays(28))
            ->selectRaw('YEARWEEK(created_at) as week')
            ->distinct()
            ->count();

        $consistencyBonus = min(0.3, ($weeklyPresence / 4) * 0.3);

        return ($baseScore + $consistencyBonus) * $dilutionFactor;
    }

    /**
     * Get engagement tier for a score.
     *
     * @param  float  $score
     * @return string  One of: "cold", "warm", "hot", "critical"
     */
    public function getTier(float $score): string
    {
        return match (true) {
            $score >= 0.8 => 'critical',
            $score >= 0.5 => 'hot',
            $score >= 0.2 => 'warm',
            default => 'cold',
        };
    }
}
