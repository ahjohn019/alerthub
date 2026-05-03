<?php

namespace App\Services\AlertProcessing\Handlers;

use App\Services\AlertProcessing\HandlerResult;
use App\Services\AlertProcessing\WebhookProcessingContext;

abstract class Handler
{
    abstract public function handle(WebhookProcessingContext $context): HandlerResult;
}
