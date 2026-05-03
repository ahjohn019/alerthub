<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\PaginatesApiResources;
use App\Http\Concerns\ParsesIncludes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListSubscribersRequest;
use App\Http\Requests\Api\StoreSubscriberRequest;
use App\Http\Resources\SubscriberResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectSubscriberController extends Controller
{
    use PaginatesApiResources;
    use ParsesIncludes;

    public function index(ListSubscribersRequest $request, Project $project): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $subscribers = $project->subscribers()
            ->with($this->includes($request, [
                'notifications' => 'notifications',
            ]))
            ->latest();

        return SubscriberResource::collection($this->paginate($subscribers, $validated));
    }

    public function store(StoreSubscriberRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();

        $subscriber = $project->subscribers()->create($validated);

        return (new SubscriberResource($subscriber))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
