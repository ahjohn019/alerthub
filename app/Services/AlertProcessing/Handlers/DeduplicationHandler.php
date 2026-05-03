<?php

namespace App\Services\AlertProcessing\Handlers;

use App\Services\AlertProcessing\HandlerResult;
use App\Services\AlertProcessing\WebhookProcessingContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeduplicationHandler extends Handler
{
    public function handle(WebhookProcessingContext $context): HandlerResult
    {
        $key = 'webhook-event:'.$context->eventHash();

        // Write into log files when duplicate event is detected, but do not create an alert for it
        if (Cache::has($key)) {
            Log::info('Duplicate webhook event ignored.', [
                'source_id' => $context->source->id,
                'project_id' => $context->projectId(),
                'event_type' => $context->eventType(),
            ]);

            return HandlerResult::QUIT;
        }

        // Mark this event as processed for the next 5 minutes to prevent duplicates
        Cache::put($key, true, now()->addMinutes(5));

        return HandlerResult::CONTINUE;
    }
}
