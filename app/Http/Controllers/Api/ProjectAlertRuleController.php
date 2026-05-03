<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\PaginatesApiResources;
use App\Http\Concerns\ParsesIncludes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListAlertRulesRequest;
use App\Http\Requests\Api\StoreAlertRuleRequest;
use App\Http\Resources\AlertRuleResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectAlertRuleController extends Controller
{
    use PaginatesApiResources;
    use ParsesIncludes;

    public function index(ListAlertRulesRequest $request, Project $project): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $rules = $project->alertRules()
            ->with($this->includes($request, [
                'notifications' => 'notifications',
            ]))
            ->when($validated['source_type'] ?? null, fn ($query, $value) => $query->where('source_type', $value))
            ->when($validated['event_type'] ?? null, fn ($query, $value) => $query->where('event_type', $value))
            ->when(array_key_exists('is_active', $validated), fn ($query) => $query->where('is_active', $validated['is_active']))
            ->latest();

        return AlertRuleResource::collection($this->paginate($rules, $validated));
    }

    public function store(StoreAlertRuleRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();

        $rule = $project->alertRules()->create($validated);

        return (new AlertRuleResource($rule))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
