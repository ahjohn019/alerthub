<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class SubscriberApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriber_index_returns_engagement_fields_and_included_notifications(): void
    {
        $organization = Organization::factory()->create();
        $project = Project::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $subscriber = Subscriber::factory()->create([
            'project_id' => $project->id,
            'notification_count' => 4,
            'last_notified_at' => now()->subDay(),
            'metadata' => ['team' => 'ops'],
        ]);
        $notification = Notification::factory()->create([
            'project_id' => $project->id,
            'subscriber_id' => $subscriber->id,
            'status' => 'sent',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$organization->api_token}")
            ->getJson("/api/projects/{$project->id}/subscribers?includes=notifications");

        $response->assertOk()
            ->assertJson(function (AssertableJson $json) use ($subscriber, $notification): void {
                $json->has('data', 1)
                    ->where('data.0.id', $subscriber->id)
                    ->where('data.0.metadata.team', 'ops')
                    ->whereType('data.0.engagement_score', 'double')
                    ->whereType('data.0.engagement_tier', 'string')
                    ->where('data.0.notifications.0.id', $notification->id)
                    ->etc();
            });
    }
}
