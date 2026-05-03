<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\PaginatesApiResources;
use App\Http\Concerns\ParsesIncludes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectNotificationController extends Controller
{
    use PaginatesApiResources;
    use ParsesIncludes;

    public function index(ListNotificationsRequest $request, Project $project): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $notifications = $project->notifications()
            ->with($this->includes($request, [
                'subscriber' => 'subscriber',
                'alert_rule' => 'alertRule',
            ]))
            ->when($validated['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($validated['channel'] ?? null, fn ($query, $value) => $query->where('channel', $value))
            ->when($validated['subscriber_id'] ?? null, fn ($query, $value) => $query->where('subscriber_id', $value))
            ->when($validated['alert_rule_id'] ?? null, fn ($query, $value) => $query->where('alert_rule_id', $value))
            ->latest();

        return NotificationResource::collection($this->paginate($notifications, $validated));
    }
}
