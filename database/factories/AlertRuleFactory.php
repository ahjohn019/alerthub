<?php

namespace Database\Factories;

use App\Models\AlertRule;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertRule>
 */
class AlertRuleFactory extends Factory
{
    protected $model = AlertRule::class;

    public function definition(): array
    {
        $sourceType = fake()->randomElement(['github', 'stripe', 'monitoring', 'custom']);

        return [
            'project_id' => Project::factory(),
            'name' => fake()->sentence(3),
            'source_type' => $sourceType,
            'event_type' => match ($sourceType) {
                'github' => 'push',
                'stripe' => 'payment_intent.payment_failed',
                'monitoring' => 'alert.triggered',
                default => 'event.received',
            },
            'conditions' => [
                'severity' => fake()->randomElement(['medium', 'high', 'critical']),
            ],
            'action' => fake()->randomElement(['notify', 'escalate', 'digest']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'is_active' => true,
        ];
    }
}
