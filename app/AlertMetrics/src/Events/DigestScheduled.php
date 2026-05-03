<?php

namespace AlertMetrics\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DigestScheduled
{
    use Dispatchable, SerializesModels;

    public int $subscriberId;
    public int $projectId;
    public string $date;
    public array $alertIds;
    public string $digestType;

    /**
     * Properties set by listeners during the event pipeline.
     */
    public ?string $referenceId = null;
    public ?string $scheduledWindow = null;
    public ?string $priority = null;

    public function __construct(
        int $subscriberId,
        int $projectId,
        string $date,
        array $alertIds,
        string $digestType = 'daily'
    ) {
        $this->subscriberId = $subscriberId;
        $this->projectId = $projectId;
        $this->date = $date;
        $this->alertIds = $alertIds;
        $this->digestType = $digestType;
    }
}
