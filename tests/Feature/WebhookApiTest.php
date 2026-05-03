<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookEvent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\WebhookSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_endpoint_accepts_a_valid_event_and_dispatches_the_job(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        $project = Project::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $source = WebhookSource::factory()->unsigned()->create([
            'project_id' => $project->id,
            'source_key' => 'github-main',
            'source_type' => 'github',
            'is_active' => true,
        ]);

        $payload = [
            'event_type' => 'push',
            'source' => 'github',
            'payload' => [
                'ref' => 'refs/heads/main',
                'sender' => [
                    'login' => 'janedev',
                    'email' => 'jane@example.com',
                ],
            ],
        ];

        $response = $this->postJson("/api/webhooks/{$project->uuid}/{$source->source_key}", $payload);

        $response->assertAccepted()
            ->assertJson([
                'message' => 'Webhook accepted.',
            ]);

        Queue::assertPushed(ProcessWebhookEvent::class, function (ProcessWebhookEvent $job) use ($source, $payload): bool {
            return $job->sourceId === $source->id
                && $job->payload === $payload;
        });
    }
}
