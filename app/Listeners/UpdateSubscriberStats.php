<?php

namespace App\Listeners;

use AlertMetrics\MetricsAggregator;
use App\Events\NotificationCreated;
use App\Models\Subscriber;

class UpdateSubscriberStats
{
    public function __construct(
        private readonly MetricsAggregator $aggregator,
    ) {}

    public function handle(NotificationCreated $event): void
    {
        $subscriber = Subscriber::find($event->notification->subscriber_id);

        if (!$subscriber) {
            return;
        }

        $subscriber->increment('notification_count');
        $subscriber->update(['last_notified_at' => now()]);
        $this->aggregator->recordAlert($event->notification->id);
    }
}
