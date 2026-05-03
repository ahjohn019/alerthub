<?php

namespace App\Jobs;

use App\Models\WebhookSource;
use App\Services\AlertProcessing\Pipeline;
use App\Services\AlertProcessing\WebhookProcessingContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWebhookEvent implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function __construct(
        public readonly int $sourceId,
        public readonly array $payload,
        public readonly array $headers = [],
    ) {}

    public function handle(): void
    {
        $source = WebhookSource::withoutGlobalScopes()
            ->with('project')
            ->findOrFail($this->sourceId);

        Pipeline::default()->run(new WebhookProcessingContext(
            source: $source,
            payload: $this->payload,
            headers: $this->headers,
        ));
    }

    public function uniqueId(): string
    {
        return $this->sourceId.':'.hash('sha256', json_encode($this->payload, JSON_THROW_ON_ERROR));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Webhook processing job failed.', [
            'source_id' => $this->sourceId,
            'message' => $exception->getMessage(),
        ]);
    }
}
