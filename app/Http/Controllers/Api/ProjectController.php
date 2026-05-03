<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\PaginatesApiResources;
use App\Http\Concerns\ParsesIncludes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListProjectsRequest;
use App\Http\Requests\Api\StoreProjectRequest;
use App\Http\Requests\Api\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    use PaginatesApiResources;
    use ParsesIncludes;

    private const INCLUDES = [
        'subscribers' => 'subscribers',
        'alert_rules' => 'alertRules',
        'notifications' => 'notifications',
        'webhook_sources' => 'webhookSources',
    ];

    public function index(ListProjectsRequest $request, TenantContext $tenant): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $projects = Project::query()
            ->forOrganization($tenant->organizationId())
            ->with($this->includes($request, self::INCLUDES))
            ->latest();

        return ProjectResource::collection($this->paginate($projects, $validated));
    }

    public function store(StoreProjectRequest $request, TenantContext $tenant): JsonResponse
    {
        $validated = $request->validated();

        $project = Project::query()->create([
            ...$validated,
            'organization_id' => $tenant->organizationId(),
        ]);

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Project $project): ProjectResource
    {
        $project->load($this->includes($request, self::INCLUDES));

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $validated = $request->validated();

        $project->update($validated);

        return new ProjectResource($project);
    }
}
