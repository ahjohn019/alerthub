<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'subscribers' => SubscriberResource::collection($this->whenLoaded('subscribers')),
            'alert_rules' => AlertRuleResource::collection($this->whenLoaded('alertRules')),
            'notifications' => NotificationResource::collection($this->whenLoaded('notifications')),
            'webhook_sources' => WebhookSourceResource::collection($this->whenLoaded('webhookSources')),
        ];
    }
}
