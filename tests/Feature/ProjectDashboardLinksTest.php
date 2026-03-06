<?php

use App\Models\Milestone;
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

test('milestone rows link to milestone detail page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create([
        'project_id' => $project->id,
        'title' => 'Clickable Milestone',
        'status' => 'open',
        'due_date' => '2026-04-15',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('Clickable Milestone');
    $response->assertSee(route('projects.milestones.show', ['project' => $project, 'milestone' => $milestone]));
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
