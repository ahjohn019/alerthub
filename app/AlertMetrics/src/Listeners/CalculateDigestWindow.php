<?php

namespace AlertMetrics\Listeners;

use AlertMetrics\Events\DigestScheduled;
use Illuminate\Support\Facades\Log;

class CalculateDigestWindow
{
    /**
     * Calculate the delivery window for the digest.
     *
     * Uses the reference ID to determine optimal send timing based on
     * subscriber engagement patterns and alert volume.
     *
     * Requires: reference_id to be set on the event (by GenerateDigestId).
     */
    public function handle(DigestScheduled $event): void
    {
        // reference_id is required to calculate the window hash
        if (!$event->referenceId) {
            Log::debug('CalculateDigestWindow: No reference_id set, skipping window calculation');
            return;
        }

        $alertCount = count($event->alertIds);

        // High-volume digests should be sent sooner
        if ($alertCount > 20) {
            $window = 'immediate';
        } elseif ($alertCount > 5) {
            $window = 'within_1h';
        } else {
            $window = 'next_batch';
        }

        $event->scheduledWindow = $window;

        Log::info('CalculateDigestWindow: Window calculated', [
            'reference_id' => $event->referenceId,
            'subscriber_id' => $event->subscriberId,
            'alert_count' => $alertCount,
            'window' => $window,
        ]);
    }
}
