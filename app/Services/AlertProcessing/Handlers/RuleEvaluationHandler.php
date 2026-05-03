<?php

namespace App\Services\AlertProcessing\Handlers;

use App\Models\AlertRule;
use App\Services\AlertProcessing\HandlerResult;
use App\Services\AlertProcessing\WebhookProcessingContext;
use Illuminate\Support\Facades\Log;

class RuleEvaluationHandler extends Handler
{
    public function handle(WebhookProcessingContext $context): HandlerResult
    {
        $rules = AlertRule::withoutGlobalScopes()
            ->where('project_id', $context->projectId())
            ->where('source_type', $context->sourceType())
            ->where('event_type', $context->eventType())
            ->where('is_active', true)
            ->get()
            ->filter(fn (AlertRule $rule): bool => $this->matchesConditions($rule, $context->payload))
            ->values();

        if ($rules->isEmpty()) {
            Log::info('Webhook event did not match any active alert rules.', [
                'source_id' => $context->source->id,
                'project_id' => $context->projectId(),
                'event_type' => $context->eventType(),
            ]);

            return HandlerResult::QUIT;
        }

        $context->matchedRules = $rules;

        return HandlerResult::CONTINUE;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function matchesConditions(AlertRule $rule, array $payload): bool
    {
        foreach ($rule->conditions ?? [] as $key => $expected) {
            $actual = data_get($payload, 'payload.'.$key);

            if ($key === 'branch') {
                $actual = str_replace('refs/heads/', '', (string) data_get($payload, 'payload.ref'));
            }

            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }
}
