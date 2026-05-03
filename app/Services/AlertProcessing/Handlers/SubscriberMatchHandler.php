<?php

namespace App\Services\AlertProcessing\Handlers;

use AlertMetrics\SubscriberResolver;
use App\Services\AlertProcessing\HandlerResult;
use App\Services\AlertProcessing\WebhookProcessingContext;
use Illuminate\Support\Facades\Log;

class SubscriberMatchHandler extends Handler
{
    public function __construct(
        private readonly SubscriberResolver $resolver,
    ) {}

    public function handle(WebhookProcessingContext $context): HandlerResult
    {
        $context->subscriber = $this->resolver->resolve($context->projectId(), $context->payload);

        if (! $context->subscriber) {
            Log::warning('Webhook event could not be matched to a subscriber.', [
                'source_id' => $context->source->id,
                'project_id' => $context->projectId(),
                'event_type' => $context->eventType(),
            ]);

            return HandlerResult::QUIT;
        }

        return HandlerResult::CONTINUE;
    }
}
