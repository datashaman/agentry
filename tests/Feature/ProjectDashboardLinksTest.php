<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\User;

test('repos count card links to repos list page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Repo::factory()->count(2)->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee(route('projects.repos.index', $project));
});

test('all summary stat cards have correct links', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee(route('projects.ops-requests.index', $project));
    $response->assertSee(route('projects.repos.index', $project));
});
