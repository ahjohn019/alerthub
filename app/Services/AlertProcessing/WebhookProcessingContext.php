<?php

namespace App\Services\AlertProcessing;

use App\Models\AlertRule;
use App\Models\Subscriber;
use App\Models\WebhookSource;
use Illuminate\Support\Collection;

class WebhookProcessingContext
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function __construct(
        public readonly WebhookSource $source,
        public readonly array $payload,
        public readonly array $headers = [],
        public ?Subscriber $subscriber = null,
        public Collection $matchedRules = new Collection(),
    ) {}

    public function projectId(): int
    {
        return $this->source->project_id;
    }

    public function sourceType(): string
    {
        return $this->source->source_type;
    }

    public function eventType(): string
    {
        return (string) data_get($this->payload, 'event_type');
    }

    public function eventHash(): string
    {
        return hash('sha256', json_encode([
            'source_id' => $this->source->id,
            'event_type' => $this->eventType(),
            'payload' => $this->payload,
        ], JSON_THROW_ON_ERROR));
    }
}
