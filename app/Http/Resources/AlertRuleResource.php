<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'source_type' => $this->source_type,
            'event_type' => $this->event_type,
            'conditions' => $this->conditions,
            'action' => $this->action,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'notifications' => NotificationResource::collection($this->whenLoaded('notifications')),
        ];
    }
}
