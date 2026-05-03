<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\WebhookSource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $organizations = collect([
            Organization::factory()->create([
                'name' => 'Acme Alerts',
                'api_token' => 'acme-alerts-token',
                'plan' => 'enterprise',
                'timezone' => 'America/New_York',
            ]),
            Organization::factory()->create([
                'name' => 'Globex Monitoring',
                'api_token' => 'globex-monitoring-token',
                'plan' => 'pro',
                'timezone' => 'Asia/Kuala_Lumpur',
            ]),
        ]);

        $organizations->each(function (Organization $organization): void {
            Project::factory()
                ->count(2)
                ->for($organization)
                ->create()
                ->each(function (Project $project): void {
                    $subscribers = Subscriber::factory()
                        ->count(4)
                        ->for($project)
                        ->create();

                    $rules = collect([
                        AlertRule::factory()->for($project)->create([
                            'name' => 'GitHub deploy alerts',
                            'source_type' => 'github',
                            'event_type' => 'push',
                            'conditions' => ['branch' => 'main'],
                            'action' => 'notify',
                            'priority' => 'medium',
                        ]),
                        AlertRule::factory()->for($project)->create([
                            'name' => 'Critical monitoring incidents',
                            'source_type' => 'monitoring',
                            'event_type' => 'alert.triggered',
                            'conditions' => ['severity' => 'critical'],
                            'action' => 'escalate',
                            'priority' => 'critical',
                        ]),
                        AlertRule::factory()->for($project)->create([
                            'name' => 'Daily digest alerts',
                            'source_type' => 'monitoring',
                            'event_type' => 'alert.triggered',
                            'conditions' => [],
                            'action' => 'digest',
                            'priority' => 'low',
                        ]),
                    ]);

                    WebhookSource::factory()->for($project)->unsigned()->create([
                        'source_key' => 'github',
                        'source_type' => 'github',
                        'name' => 'GitHub',
                    ]);

                    WebhookSource::factory()->for($project)->unsigned()->create([
                        'source_key' => 'monitoring',
                        'source_type' => 'monitoring',
                        'name' => 'Monitoring',
                    ]);

                    foreach ($subscribers->take(2) as $subscriber) {
                        Notification::factory()->create([
                            'project_id' => $project->id,
                            'subscriber_id' => $subscriber->id,
                            'alert_rule_id' => $rules->random()->id,
                            'status' => 'sent',
                            'sent_at' => now()->subDay(),
                        ]);
                    }
                });
        });
    }
}
