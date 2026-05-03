<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'email' => fake()->unique()->safeEmail(),
            'external_id' => fake()->optional()->uuid(),
            'name' => fake()->name(),
            'notification_count' => fake()->numberBetween(0, 8),
            'last_notified_at' => fake()->optional()->dateTimeBetween('-30 days'),
            'metadata' => [
                'team' => fake()->randomElement(['ops', 'billing', 'engineering']),
            ],
        ];
    }
}
