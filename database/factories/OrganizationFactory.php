<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->company(),
            'api_token' => 'org_'.Str::random(40),
            'plan' => fake()->randomElement(['free', 'pro', 'enterprise']),
            'timezone' => fake()->timezone(),
        ];
    }
}
