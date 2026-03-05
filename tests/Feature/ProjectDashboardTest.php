<?php

use App\Models\Bug;
use App\Models\Epic;
use App\Models\Milestone;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Story;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('projects.show', $project));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the project dashboard', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
});

test('project dashboard displays project name', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'My Dashboard Project']);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('My Dashboard Project');
});

test('project dashboard displays summary stats', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    Story::factory()->count(5)->create(['epic_id' => $epic->id]);
    Bug::factory()->count(3)->create(['project_id' => $project->id]);
    Repo::factory()->count(2)->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('Stories');
    $response->assertSee('Bugs');
    $response->assertSee('Repositories');
});

test('project dashboard displays epics with story counts', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id, 'title' => 'Authentication Epic']);
    Story::factory()->count(4)->create(['epic_id' => $epic->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('Epics');
    $response->assertSee('Authentication Epic');
    $response->assertSee('4 stories');
});

test('project dashboard displays active stories grouped by status', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Backlog Story', 'status' => 'backlog']);
    Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Dev Story', 'status' => 'in_development']);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('Active Stories');
    $response->assertSee('Backlog Story');
    $response->assertSee('Dev Story');
    $response->assertSee('In development');
});

test('project dashboard does not show closed stories in active list', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Closed Story', 'status' => 'closed_done']);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertDontSee('Closed Story');
});

test('project dashboard displays milestones with due dates', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Milestone::factory()->create([
        'project_id' => $project->id,
        'title' => 'Sprint 1 Milestone',
        'status' => 'open',
        'due_date' => '2026-04-15',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('Milestones');
    $response->assertSee('Sprint 1 Milestone');
    $response->assertSee('Apr 15, 2026');
});

test('project dashboard displays breadcrumbs', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Corp']);
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Widget App']);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('Acme Corp');
    $response->assertSee('Projects');
    $response->assertSee('Widget App');
});

test('project dashboard shows empty state when no epics', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertDontSee('Epics');
});

test('project dashboard shows epic with one story using singular form', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id, 'title' => 'Solo Epic']);
    Story::factory()->create(['epic_id' => $epic->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('1 story');
});
