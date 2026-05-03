<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WebhookSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookSource>
 */
class WebhookSourceFactory extends Factory
{
    protected $model = WebhookSource::class;

    public function definition(): array
    {
        $sourceType = fake()->randomElement(['github', 'stripe', 'monitoring', 'custom']);

        return [
            'project_id' => Project::factory(),
            'source_key' => fake()->unique()->slug(2),
            'source_type' => $sourceType,
            'name' => ucfirst($sourceType).' Webhook',
            'signing_secret' => fake()->optional()->sha256(),
            'event_mappings' => [
                'default_event_type' => match ($sourceType) {
                    'github' => 'push',
                    'stripe' => 'payment_intent.payment_failed',
                    'monitoring' => 'alert.triggered',
                    default => 'event.received',
                },
            ],
            'is_active' => true,
        ];
    }

    public function unsigned(): static
    {
        return $this->state(fn (): array => [
            'signing_secret' => null,
        ]);
    }
}
