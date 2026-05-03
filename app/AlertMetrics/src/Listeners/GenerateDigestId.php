<?php

namespace AlertMetrics\Listeners;

use AlertMetrics\Events\DigestScheduled;
use Illuminate\Support\Str;

class GenerateDigestId
{
    /**
     * Generate a unique reference ID for the digest.
     *
     * This ID is used for tracking, deduplication, and audit trail.
     * Must run before any listener that reads reference_id.
     */
    public function handle(DigestScheduled $event): void
    {
        $event->referenceId = sprintf(
            'DIG-%s-%s-%s',
            $event->projectId,
            $event->subscriberId,
            Str::random(8)
        );
    }
}
