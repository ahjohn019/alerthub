<?php

namespace AlertMetrics;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAlertDigest implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 10;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     *
     * @var array
     */
    public $backoff = [5, 15, 30];

    /**
     * @var int
     */
    protected int $subscriberId;

    /**
     * @var string
     */
    protected string $date;

    /**
     * @var array
     */
    protected array $alertIds;

    /**
     * @var string
     */
    protected string $digestType;

    /**
     * Create a new job instance.
     */
    public function __construct(int $subscriberId, string $date, array $alertIds, string $digestType = 'daily')
    {
        $this->subscriberId = $subscriberId;
        $this->date = $date;
        $this->alertIds = $alertIds;
        $this->digestType = $digestType;
    }

    /**
     * The unique ID of the job.
     *
     * Used by ShouldBeUnique to prevent duplicate jobs. We key on
     * subscriber and date to ensure one digest per subscriber per day.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return $this->subscriberId . ':' . $this->date . ':' . hash('sha256', json_encode([
            'digest_type' => $this->digestType,
            'alert_ids' => $this->alertIds,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Execute the job.
     */
    public function handle(MetricsAggregator $aggregator, EngagementScorer $scorer): void
    {
        $subscriber = \App\Models\Subscriber::find($this->subscriberId);

        if (!$subscriber) {
            Log::warning('ProcessAlertDigest: Subscriber not found', [
                'subscriber_id' => $this->subscriberId,
            ]);
            return;
        }

        $notifications = \App\Models\Notification::whereIn('id', $this->alertIds)
            ->where('subscriber_id', $this->subscriberId)
            ->get();

        if ($notifications->isEmpty()) {
            Log::info('ProcessAlertDigest: No notifications to digest', [
                'subscriber_id' => $this->subscriberId,
                'date' => $this->date,
            ]);
            return;
        }

        // Calculate engagement score for digest context
        $engagementScore = $scorer->calculateScore($this->subscriberId, 'digest');

        // Build digest content
        $digestContent = $this->buildDigestContent($notifications, $engagementScore);

        $digestNotification = \App\Models\Notification::withoutGlobalScopes()->create([
            'project_id' => $notifications->first()->project_id,
            'subscriber_id' => $subscriber->id,
            'alert_rule_id' => $notifications->first()->alert_rule_id,
            'channel' => 'email',
            'subject' => $digestContent['subject'],
            'body' => json_encode($digestContent['body'], JSON_THROW_ON_ERROR),
            'payload' => $digestContent['body'],
            'status' => 'pending',
        ]);

        \App\Jobs\SendNotification::dispatch($digestNotification->id);

        Log::info('ProcessAlertDigest: Digest dispatched', [
            'subscriber_id' => $this->subscriberId,
            'date' => $this->date,
            'alert_count' => $notifications->count(),
            'engagement_score' => $engagementScore,
        ]);
    }

    /**
     * Build the digest email/notification content.
     */
    protected function buildDigestContent($notifications, float $engagementScore): array
    {
        $alertCount = $notifications->count();
        $highPriority = $notifications->where('payload.priority', 'critical')->count();

        $subject = sprintf(
            'Alert Digest: %d alert%s for %s',
            $alertCount,
            $alertCount === 1 ? '' : 's',
            $this->date
        );

        $body = [
            'total_alerts' => $alertCount,
            'high_priority' => $highPriority,
            'engagement_score' => $engagementScore,
            'digest_type' => $this->digestType,
            'alerts' => $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'subject' => $notification->subject,
                    'status' => $notification->status,
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            })->toArray(),
        ];

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAlertDigest: Job failed', [
            'subscriber_id' => $this->subscriberId,
            'date' => $this->date,
            'alert_count' => count($this->alertIds),
            'error' => $exception->getMessage(),
        ]);
    }
}
