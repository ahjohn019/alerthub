<?php

namespace Tests\Unit;

use AlertMetrics\DigestScheduler;
use AlertMetrics\Events\DigestScheduled;
use AlertMetrics\MetricsAggregator;
use AlertMetrics\ProcessAlertDigest;
use AlertMetrics\SubscriberResolver;
use AlertMetrics\EngagementScorer;
use App\Models\AlertRule;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AlertMetricsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_aggregator_scopes_counts_by_project(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $subscriberA = Subscriber::factory()->create(['project_id' => $projectA->id]);
        $subscriberB = Subscriber::factory()->create(['project_id' => $projectB->id]);

        $ruleA = AlertRule::factory()->create(['project_id' => $projectA->id]);
        $ruleB = AlertRule::factory()->create(['project_id' => $projectB->id]);

        Notification::factory()->create([
            'uuid' => fake()->uuid(),
            'project_id' => $projectA->id,
            'subscriber_id' => $subscriberA->id,
            'alert_rule_id' => $ruleA->id,
            'channel' => 'email',
            'subject' => 'Project A alert 1',
            'body' => 'Body',
            'payload' => ['priority' => 'high'],
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now()->setHour(9),
            'updated_at' => now(),
        ]);
        Notification::factory()->create([
            'uuid' => fake()->uuid(),
            'project_id' => $projectA->id,
            'subscriber_id' => $subscriberA->id,
            'alert_rule_id' => $ruleA->id,
            'channel' => 'email',
            'subject' => 'Project A alert 2',
            'body' => 'Body',
            'payload' => ['priority' => 'critical'],
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now()->setHour(10),
            'updated_at' => now(),
        ]);
        Notification::factory()->create([
            'uuid' => fake()->uuid(),
            'project_id' => $projectB->id,
            'subscriber_id' => $subscriberB->id,
            'alert_rule_id' => $ruleB->id,
            'channel' => 'email',
            'subject' => 'Project B alert',
            'body' => 'Body',
            'payload' => ['priority' => 'low'],
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now()->setHour(11),
            'updated_at' => now(),
        ]);

        $aggregator = app(MetricsAggregator::class);

        $this->assertSame(2, $aggregator->getDailyAlertCount($projectA->id, now()->toDateString()));
        $this->assertSame([9 => 1, 10 => 1], $aggregator->getHourlyBreakdown($projectA->id, now()->toDateString()));
        $this->assertSame(1, $aggregator->getDailyAlertCount($projectB->id, now()->toDateString()));
    }

    public function test_subscriber_resolver_uses_external_id_when_email_is_missing(): void
    {
        $project = Project::factory()->create();

        $existing = Subscriber::withoutGlobalScopes()->create([
            'project_id' => $project->id,
            'email' => null,
            'external_id' => 'existing-contact',
            'name' => 'Existing Contact',
            'notification_count' => 0,
            'metadata' => [],
        ]);

        $resolver = app(SubscriberResolver::class);
        $payload = [
            'source' => 'monitoring',
            'event_type' => 'alert.triggered',
            'payload' => [
                'contact' => [
                    'external_id' => 'ops-team',
                    'name' => 'Ops Team',
                ],
            ],
        ];

        $resolved = $resolver->resolve($project->id, $payload);
        $resolvedAgain = $resolver->resolve($project->id, $payload);

        $this->assertNotNull($resolved);
        $this->assertSame('ops-team', $resolved->external_id);
        $this->assertSame('Ops Team', $resolved->name);
        $this->assertSame(['source' => 'monitoring', 'first_seen_event' => 'alert.triggered', 'created_via' => 'webhook'], $resolved->metadata);
        $this->assertNotSame($existing->id, $resolved->id);
        $this->assertSame($resolved->id, $resolvedAgain->id);
        $this->assertCount(2, Subscriber::withoutGlobalScopes()->where('project_id', $project->id)->get());
    }

    public function test_digest_scheduled_listeners_populate_window_before_priority(): void
    {
        $event = new DigestScheduled(
            subscriberId: 10,
            projectId: 20,
            date: now()->toDateString(),
            alertIds: [1, 2, 3, 4, 5, 6],
            digestType: 'daily'
        );

        event($event);

        $this->assertNotNull($event->referenceId);
        $this->assertSame('within_1h', $event->scheduledWindow);
        $this->assertSame('medium', $event->priority);
    }

    public function test_process_alert_digest_unique_id_includes_alert_batch(): void
    {
        $jobOne = new ProcessAlertDigest(1, now()->toDateString(), [1, 2, 3], 'daily');
        $jobTwo = new ProcessAlertDigest(1, now()->toDateString(), [4, 5, 6], 'daily');

        $this->assertNotSame($jobOne->uniqueId(), $jobTwo->uniqueId());
    }

    public function test_engagement_scores_do_not_bleed_between_contexts(): void
    {
        Cache::flush();

        $project = Project::factory()->create();
        $subscriber = Subscriber::withoutGlobalScopes()->create([
            'project_id' => $project->id,
            'email' => 'subscriber@example.com',
            'external_id' => 'subscriber-1',
            'name' => 'Subscriber',
            'notification_count' => 100,
            'last_notified_at' => now(),
            'metadata' => [],
        ]);
        $rule = AlertRule::factory()->create(['project_id' => $project->id]);

        foreach ([0, 7, 14, 21] as $daysAgo) {
            Notification::factory()->create([
                'uuid' => fake()->uuid(),
                'project_id' => $project->id,
                'subscriber_id' => $subscriber->id,
                'alert_rule_id' => $rule->id,
                'channel' => 'email',
                'subject' => "Alert {$daysAgo}",
                'body' => 'Body',
                'payload' => ['priority' => 'high'],
                'status' => 'sent',
                'sent_at' => now()->subDays($daysAgo),
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now(),
            ]);
        }

        $scorer = app(EngagementScorer::class);
        $digestScore = $scorer->calculateScore($subscriber->id, 'digest');
        $realtimeScore = $scorer->calculateScore($subscriber->id, 'realtime');

        $this->assertNotSame($digestScore, $realtimeScore);
    }
}
