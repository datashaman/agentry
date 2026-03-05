<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Epic;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\Team;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('projects.bugs.index', $project));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the bugs page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', $project));
    $response->assertOk();
});

test('bugs page displays bugs for the project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Bug::factory()->create(['project_id' => $project->id, 'title' => 'Login Crash']);
    Bug::factory()->create(['project_id' => $project->id, 'title' => 'Layout Broken']);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', $project));
    $response->assertOk();
    $response->assertSee('Login Crash');
    $response->assertSee('Layout Broken');
});

test('bugs page does not display bugs from other projects', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $otherProject = Project::factory()->create(['organization_id' => $organization->id]);
    Bug::factory()->create(['project_id' => $otherProject->id, 'title' => 'Secret Bug']);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', $project));
    $response->assertOk();
    $response->assertDontSee('Secret Bug');
});

test('bugs page shows bug status, severity, and priority', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Bug::factory()->create([
        'project_id' => $project->id,
        'title' => 'Test Bug',
        'status' => 'triaged',
        'severity' => 'critical',
        'priority' => 1,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', $project));
    $response->assertOk();
    $response->assertSee('Test Bug');
    $response->assertSee('triaged');
    $response->assertSee('Critical');
    $response->assertSee('P1');
});

test('bugs page shows linked story title', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Login Feature']);
    Bug::factory()->create([
        'project_id' => $project->id,
        'title' => 'Login Bug',
        'linked_story_id' => $story->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', $project));
    $response->assertOk();
    $response->assertSee('Login Feature');
});

test('bugs page shows assigned agent name', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Fix Bot']);
    Bug::factory()->create([
        'project_id' => $project->id,
        'title' => 'Agent Bug',
        'assigned_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', $project));
    $response->assertOk();
    $response->assertSee('Fix Bot');
});

test('bugs page filters by status', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Bug::factory()->create(['project_id' => $project->id, 'title' => 'New Bug', 'status' => 'new']);
    Bug::factory()->create(['project_id' => $project->id, 'title' => 'Triaged Bug', 'status' => 'triaged']);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', [$project, 'status' => 'new']));
    $response->assertOk();
    $response->assertSee('New Bug');
    $response->assertDontSee('Triaged Bug');
});

test('bugs page filters by severity', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Bug::factory()->create(['project_id' => $project->id, 'title' => 'Critical Bug', 'severity' => 'critical']);
    Bug::factory()->create(['project_id' => $project->id, 'title' => 'Minor Bug', 'severity' => 'minor']);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', [$project, 'severity' => 'critical']));
    $response->assertOk();
    $response->assertSee('Critical Bug');
    $response->assertDontSee('Minor Bug');
});

test('bugs page filters by priority', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Bug::factory()->create(['project_id' => $project->id, 'title' => 'P1 Bug', 'priority' => 1]);
    Bug::factory()->create(['project_id' => $project->id, 'title' => 'P5 Bug', 'priority' => 5]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', [$project, 'priority' => '1']));
    $response->assertOk();
    $response->assertSee('P1 Bug');
    $response->assertDontSee('P5 Bug');
});

test('bugs page sorts by severity by default', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Bug::factory()->create(['project_id' => $project->id, 'title' => 'Trivial Bug', 'severity' => 'trivial']);
    Bug::factory()->create(['project_id' => $project->id, 'title' => 'Critical Bug', 'severity' => 'critical']);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', $project));
    $response->assertOk();
    $response->assertSeeInOrder(['Critical Bug', 'Trivial Bug']);
});

test('bugs page links to bug detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', $project));
    $response->assertOk();
    $response->assertSee(route('projects.bugs.show', [$project, $bug]));
});

test('bugs page shows no bugs message when none exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.index', $project));
    $response->assertOk();
    $response->assertSee('No Bugs');
});
