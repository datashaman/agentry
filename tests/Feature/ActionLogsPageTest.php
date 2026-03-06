<?php

use App\Models\ActionLog;
use App\Models\Agent;
use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

test('action logs page displays project action logs', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);
    $agent = Agent::factory()->create();
    ActionLog::factory()->create([
        'agent_id' => $agent->id,
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'action' => 'created_branch',
        'reasoning' => 'Starting work on the feature',
        'timestamp' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.action-logs.index', $project));
    $response->assertOk();
    $response->assertSee('action-logs-table');
    $response->assertSee('created_branch');
    $response->assertSee($agent->name);
    $response->assertSee('Starting work on the feature');
});

test('action logs page has filters', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.action-logs.index', $project));
    $response->assertOk();
    $response->assertSee('filter-agent');
    $response->assertSee('filter-action');
    $response->assertSee('filter-work-item-type');
});

test('action logs page filters by agent', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);
    $agent1 = Agent::factory()->create(['name' => 'Agent Alpha']);
    $agent2 = Agent::factory()->create(['name' => 'Agent Beta']);
    ActionLog::factory()->create(['agent_id' => $agent1->id, 'work_item_id' => $opsRequest->id, 'work_item_type' => OpsRequest::class, 'reasoning' => 'Alpha reasoning']);
    ActionLog::factory()->create(['agent_id' => $agent2->id, 'work_item_id' => $opsRequest->id, 'work_item_type' => OpsRequest::class, 'reasoning' => 'Beta reasoning']);

    $this->actingAs($user);

    $response = $this->get(route('projects.action-logs.index', [$project, 'agent' => $agent1->id]));
    $response->assertOk();
    $response->assertSee('Alpha reasoning');
    $response->assertDontSee('Beta reasoning');
});

test('action logs page is accessible from project dashboard', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee(route('projects.action-logs.index', $project));
});

test('action logs page shows work item type in table', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);
    ActionLog::factory()->create(['work_item_id' => $opsRequest->id, 'work_item_type' => OpsRequest::class]);

    $this->actingAs($user);

    $response = $this->get(route('projects.action-logs.index', $project));
    $response->assertOk();
    $response->assertSee('action-logs-table');
});
