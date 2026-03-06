<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Critique;
use App\Models\Epic;
use App\Models\HitlEscalation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\Team;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the bug detail page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
});

test('bug detail page displays bug header with title, status, severity, priority, environment, and agent', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Fix Bot']);
    $bug = Bug::factory()->create([
        'project_id' => $project->id,
        'title' => 'Login button unresponsive',
        'status' => 'in_progress',
        'severity' => 'critical',
        'priority' => 1,
        'environment' => 'production',
        'assigned_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('Login button unresponsive');
    $response->assertSee('in progress');
    $response->assertSee('critical');
    $response->assertSee('P1');
    $response->assertSee('production');
    $response->assertSee('Fix Bot');
});

test('bug detail page displays description', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create([
        'project_id' => $project->id,
        'description' => 'The login button does not respond to clicks on mobile Safari.',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('The login button does not respond to clicks on mobile Safari.');
});

test('bug detail page displays reproduction steps', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create([
        'project_id' => $project->id,
        'repro_steps' => '1. Open Safari on iOS\n2. Navigate to login\n3. Tap the login button',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('Reproduction Steps');
    $response->assertSee('1. Open Safari on iOS');
});

test('bug detail page displays linked story', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create([
        'epic_id' => $epic->id,
        'title' => 'Implement Login Flow',
    ]);
    $bug = Bug::factory()->create([
        'project_id' => $project->id,
        'linked_story_id' => $story->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('Linked Story');
    $response->assertSee('Implement Login Flow');
});

test('bug detail page displays critiques', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    Critique::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'critic_type' => 'code',
        'severity' => 'blocking',
        'disposition' => 'pending',
        'revision' => 1,
        'issues' => ['Missing null check'],
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('Critiques');
    $response->assertSee('code');
    $response->assertSee('blocking');
    $response->assertSee('pending');
    $response->assertSee('Rev 1');
    $response->assertSee('Missing null check');
});

test('bug detail page displays HITL escalations (resolved and unresolved)', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Triage Bot']);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'confidence',
        'reason' => 'Unclear severity assessment',
    ]);

    HitlEscalation::factory()->resolved()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'risk',
        'reason' => 'Production hotfix needed',
        'resolution' => 'Approved for immediate deploy',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('HITL Escalations');
    $response->assertSee('confidence');
    $response->assertSee('Unclear severity assessment');
    $response->assertSee('Unresolved');
    $response->assertSee('risk');
    $response->assertSee('Production hotfix needed');
    $response->assertSee('Resolved');
    $response->assertSee('Approved for immediate deploy');
    $response->assertSee('Triage Bot');
});

test('bug detail page shows breadcrumbs with organization and project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee($organization->name);
    $response->assertSee($project->name);
});
