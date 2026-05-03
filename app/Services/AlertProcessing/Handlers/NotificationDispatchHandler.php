<?php

namespace App\Services\AlertProcessing\Handlers;

use App\Jobs\SendNotification;
use App\Models\AlertRule;
use App\Models\Notification;
use App\Services\AlertProcessing\HandlerResult;
use App\Services\AlertProcessing\WebhookProcessingContext;

class NotificationDispatchHandler extends Handler
{
    public function handle(WebhookProcessingContext $context): HandlerResult
    {
        if (! $context->subscriber) {
            return HandlerResult::QUIT;
        }

        $context->matchedRules->each(function (AlertRule $rule) use ($context): void {
            $notification = Notification::withoutGlobalScopes()->create([
                'project_id' => $context->projectId(),
                'subscriber_id' => $context->subscriber->id,
                'alert_rule_id' => $rule->id,
                'channel' => 'email',
                'subject' => $this->subjectFor($rule, $context),
                'body' => data_get($context->payload, 'payload.message'),
                'payload' => $context->payload,
                'status' => $rule->action === 'escalate' ? 'escalated' : 'pending',
            ]);

            SendNotification::dispatch($notification->id);
        });

        return HandlerResult::CONTINUE;
    }

    private function subjectFor(AlertRule $rule, WebhookProcessingContext $context): string
    {
        return sprintf('[%s] %s', strtoupper($rule->priority), $context->eventType());
    }
}
