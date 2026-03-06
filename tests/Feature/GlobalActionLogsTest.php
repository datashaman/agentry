<?php

use App\Models\ActionLog;
use App\Models\Agent;
use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

test('global action logs page displays org-scoped action logs', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'My Project']);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);
    $agent = Agent::factory()->create();
    ActionLog::factory()->create([
        'agent_id' => $agent->id,
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'action' => 'opened_pr',
        'reasoning' => 'Ready for review',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('action-logs.index'));
    $response->assertOk();
    $response->assertSee('action-logs-table');
    $response->assertSee('opened_pr');
    $response->assertSee($agent->name);
    $response->assertSee('My Project');
    $response->assertSee('Ready for review');
});

test('global action logs page has project filter', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('action-logs.index'));
    $response->assertOk();
    $response->assertSee('filter-project');
    $response->assertSee('filter-agent');
});

test('global action logs page filters by project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $project1 = Project::factory()->create(['organization_id' => $organization->id]);
    $project2 = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest1 = OpsRequest::factory()->create(['project_id' => $project1->id]);
    $opsRequest2 = OpsRequest::factory()->create(['project_id' => $project2->id]);
    ActionLog::factory()->create(['work_item_id' => $opsRequest1->id, 'work_item_type' => OpsRequest::class, 'reasoning' => 'Project one log']);
    ActionLog::factory()->create(['work_item_id' => $opsRequest2->id, 'work_item_type' => OpsRequest::class, 'reasoning' => 'Project two log']);

    $this->actingAs($user);

    $response = $this->get(route('action-logs.index', ['project' => $project1->id]));
    $response->assertOk();
    $response->assertSee('Project one log');
    $response->assertDontSee('Project two log');
});

test('global action logs page is in sidebar under Platform', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee(route('action-logs.index'));
    $response->assertSee('action-logs-nav');
});

test('global action logs page shows no organization message when no org', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('action-logs.index'));
    $response->assertOk();
    $response->assertSee('No Organization');
});
