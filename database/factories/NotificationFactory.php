<?php

namespace Database\Factories;

use App\Models\AlertRule;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'project_id' => Project::factory(),
            'subscriber_id' => Subscriber::factory(),
            'alert_rule_id' => AlertRule::factory(),
            'channel' => fake()->randomElement(['email', 'webhook']),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'payload' => [
                'event_type' => fake()->randomElement(['push', 'payment_intent.payment_failed', 'alert.triggered']),
            ],
            'status' => fake()->randomElement(['pending', 'sent', 'failed', 'escalated']),
            'sent_at' => fake()->optional()->dateTimeBetween('-14 days'),
        ];
    }
}
