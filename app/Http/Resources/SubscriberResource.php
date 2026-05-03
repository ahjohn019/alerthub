<?php

namespace App\Http\Resources;

use AlertMetrics\EngagementScorer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scorer = app(EngagementScorer::class);
        $engagementScore = $scorer->calculateScore($this->id, 'realtime');

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'email' => $this->email,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'notification_count' => $this->notification_count,
            'last_notified_at' => $this->last_notified_at,
            'metadata' => $this->metadata,
            'engagement_score' => $engagementScore,
            'engagement_tier' => $scorer->getTier($engagementScore),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'notifications' => NotificationResource::collection($this->whenLoaded('notifications')),
        ];
    }
}
