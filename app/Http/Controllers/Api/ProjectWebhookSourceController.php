<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWebhookSourceRequest;
use App\Http\Resources\WebhookSourceResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProjectWebhookSourceController extends Controller
{
    public function store(StoreWebhookSourceRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();

        $source = $project->webhookSources()->create($validated);

        return (new WebhookSourceResource($source))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
