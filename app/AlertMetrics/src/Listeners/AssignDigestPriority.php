<?php

namespace AlertMetrics\Listeners;

use AlertMetrics\Events\DigestScheduled;
use Illuminate\Support\Facades\Log;

class AssignDigestPriority
{
    /**
     * Assign priority to the digest based on alert severity and volume.
     *
     * Runs after both GenerateDigestId and CalculateDigestWindow.
     */
    public function handle(DigestScheduled $event): void
    {
        $alertCount = count($event->alertIds);

        // Priority based on volume and window
        if ($event->scheduledWindow === 'immediate') {
            $priority = 'critical';
        } elseif ($alertCount > 10) {
            $priority = 'high';
        } elseif ($alertCount > 3) {
            $priority = 'medium';
        } else {
            $priority = 'low';
        }

        $event->priority = $priority;

        Log::info('AssignDigestPriority: Priority assigned', [
            'reference_id' => $event->referenceId,
            'subscriber_id' => $event->subscriberId,
            'priority' => $priority,
            'window' => $event->scheduledWindow,
        ]);
    }
}
