<?php

namespace App\Services\AlertProcessing\Handlers;

use App\Services\AlertProcessing\HandlerResult;
use App\Services\AlertProcessing\WebhookProcessingContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ValidationHandler extends Handler
{
    public function handle(WebhookProcessingContext $context): HandlerResult
    {
        $validator = Validator::make($context->payload, $this->rulesFor($context->sourceType()));

        if ($validator->fails()) {
            Log::warning('Webhook payload failed validation.', [
                'source_id' => $context->source->id,
                'source_type' => $context->sourceType(),
                'errors' => $validator->errors()->toArray(),
            ]);

            return HandlerResult::QUIT;
        }

        return HandlerResult::CONTINUE;
    }

    private function rulesFor(string $sourceType): array
    {
        return match ($sourceType) {
            'github' => [
                'event_type' => ['required', 'string'],
                'payload.repository.full_name' => ['required', 'string'],
                'payload.commits' => ['required', 'array'],
            ],
            'stripe' => [
                'event_type' => ['required', 'string'],
                'payload.id' => ['required', 'string'],
                'payload.customer.id' => ['required', 'string'],
            ],
            'monitoring' => [
                'event_type' => ['required', 'string'],
                'payload.alert_id' => ['required', 'string'],
                'payload.contact' => ['required', 'array'],
            ],
            default => [
                'event_type' => ['required', 'string'],
                'payload' => ['required', 'array'],
            ],
        };
    }
}
