<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReceiveWebhookRequest;
use App\Jobs\ProcessWebhookEvent;
use App\Models\Project;
use App\Models\WebhookSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function store(ReceiveWebhookRequest $request, string $projectUuid, string $sourceKey): JsonResponse
    {
        $project = Project::withoutGlobalScopes()
            ->where('uuid', $projectUuid)
            ->firstOrFail();

        $source = WebhookSource::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('source_key', $sourceKey)
            ->where('is_active', true)
            ->firstOrFail();

        if ($source->signing_secret && ! $this->hasValidSignature($request, $source->signing_secret)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
                'errors' => [],
            ], Response::HTTP_UNAUTHORIZED);
        }

        ProcessWebhookEvent::dispatch(
            sourceId: $source->id,
            payload: $request->validated(),
            headers: $request->headers->all(),
        );

        return response()->json([
            'message' => 'Webhook accepted.',
        ], Response::HTTP_ACCEPTED);
    }

    private function hasValidSignature(ReceiveWebhookRequest $request, string $secret): bool
    {
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        $provided = (string) ($request->header('X-AlertHub-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? '');

        $provided = str_starts_with($provided, 'sha256=')
            ? substr($provided, 7)
            : $provided;

        return filled($provided) && hash_equals($expected, $provided);
    }
}
