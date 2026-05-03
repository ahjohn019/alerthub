<?php

namespace App\Listeners;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\Subscriber;

class CheckEscalation
{
    public function handle(NotificationCreated $event): void
    {
        $subscriber = Subscriber::withoutGlobalScopes()->find($event->notification->subscriber_id);

        if (! $subscriber) {
            return;
        }

        $threshold = (int) config('alerthub.escalation.threshold', 5);
        $windowMinutes = (int) config('alerthub.escalation.window_minutes', 60);
        $recentCount = Notification::withoutGlobalScopes()
            ->where('subscriber_id', $subscriber->id)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($subscriber->notification_count > $threshold || $recentCount > $threshold) {
            Notification::withoutGlobalScopes()
                ->whereKey($event->notification->id)
                ->update(['status' => 'escalated']);
        }
    }
}
