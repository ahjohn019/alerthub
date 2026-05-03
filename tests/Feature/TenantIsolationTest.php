<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_are_scoped_to_the_authenticated_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $projectA = Project::factory()->create([
            'organization_id' => $organizationA->id,
        ]);
        $projectB = Project::factory()->create([
            'organization_id' => $organizationB->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$organizationA->api_token}")
            ->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $projectA->id);

        $this->withHeader('Authorization', "Bearer {$organizationA->api_token}")
            ->getJson("/api/projects/{$projectB->id}")
            ->assertNotFound();
    }
}
