<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Epic;
use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Runbook;
use App\Models\RunbookStep;
use App\Models\Story;
use App\Models\Team;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the ops request detail page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
});

test('ops request detail page displays header with title, status, category, risk level, execution type, and environment', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create([
        'project_id' => $project->id,
        'title' => 'Deploy v2.5 to production',
        'status' => 'planning',
        'category' => 'deployment',
        'risk_level' => 'high',
        'execution_type' => 'supervised',
        'environment' => 'production',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee('Deploy v2.5 to production');
    $response->assertSee('planning');
    $response->assertSee('deployment');
    $response->assertSee('high');
    $response->assertSee('supervised');
    $response->assertSee('production');
});

test('ops request detail page displays description', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create([
        'project_id' => $project->id,
        'description' => 'Rolling deployment of version 2.5 with zero-downtime strategy.',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee('Rolling deployment of version 2.5 with zero-downtime strategy.');
});

test('ops request detail page displays linked stories', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Add caching layer']);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);
    $opsRequest->stories()->attach($story);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee('Linked Stories');
    $response->assertSee('Add caching layer');
});

test('ops request detail page displays linked bugs', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id, 'title' => 'Memory leak in worker']);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);
    $opsRequest->bugs()->attach($bug);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee('Linked Bugs');
    $response->assertSee('Memory leak in worker');
});

test('ops request detail page displays runbook with steps', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    $runbook = Runbook::factory()->create([
        'ops_request_id' => $opsRequest->id,
        'title' => 'Production Deploy Runbook',
        'status' => 'approved',
    ]);

    RunbookStep::factory()->create([
        'runbook_id' => $runbook->id,
        'position' => 1,
        'instruction' => 'Run database migrations',
        'status' => 'completed',
        'executed_by' => 'DevOps Bot',
    ]);

    RunbookStep::factory()->create([
        'runbook_id' => $runbook->id,
        'position' => 2,
        'instruction' => 'Deploy application containers',
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee('Runbooks');
    $response->assertSee('Production Deploy Runbook');
    $response->assertSee('approved');
    $response->assertSee('Run database migrations');
    $response->assertSee('completed');
    $response->assertSee('DevOps Bot');
    $response->assertSee('Deploy application containers');
    $response->assertSee('pending');
});

test('ops request detail page displays HITL escalations (resolved and unresolved)', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Deploy Bot']);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'risk',
        'reason' => 'High risk deployment requires approval',
    ]);

    HitlEscalation::factory()->resolved()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'policy',
        'reason' => 'Production change window policy',
        'resolution' => 'Approved by CTO',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee('HITL Escalations');
    $response->assertSee('risk');
    $response->assertSee('High risk deployment requires approval');
    $response->assertSee('Unresolved');
    $response->assertSee('policy');
    $response->assertSee('Production change window policy');
    $response->assertSee('Resolved');
    $response->assertSee('Approved by CTO');
    $response->assertSee('Deploy Bot');
});

test('ops request detail page shows breadcrumbs with organization and project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee($organization->name);
    $response->assertSee($project->name);
});

test('ops request detail page displays assigned agent', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Infra Agent']);
    $opsRequest = OpsRequest::factory()->create([
        'project_id' => $project->id,
        'assigned_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee('Infra Agent');
});
