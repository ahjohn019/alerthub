<?php

namespace App\Services\AlertProcessing;

use App\Services\AlertProcessing\Handlers\Handler;
use App\Services\AlertProcessing\Handlers\NotificationDispatchHandler;

class Pipeline
{
    /**
     * @param  array<class-string<Handler>>  $handlers
     */
    public function __construct(
        private readonly array $handlers,
    ) {}

    public static function default(): self
    {
        return new self([
            Handlers\DeduplicationHandler::class,
            Handlers\ValidationHandler::class,
            Handlers\SubscriberMatchHandler::class,
            Handlers\RuleEvaluationHandler::class,
            NotificationDispatchHandler::class,
        ]);
    }

    public function run(WebhookProcessingContext $context): HandlerResult
    {
        foreach ($this->handlers as $handlerClass) {
            /** @var Handler $handler */
            $handler = app($handlerClass);
            $result = $handler->handle($context);

            if ($result === HandlerResult::QUIT) {
                return $result;
            }

            if ($result === HandlerResult::SKIP_TO_DISPATCH) {
                app(NotificationDispatchHandler::class)->handle($context);

                return $result;
            }
        }

        return HandlerResult::CONTINUE;
    }
}
