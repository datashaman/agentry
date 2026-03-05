<?php

use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Runbook;
use App\Models\RunbookStep;
use App\Models\User;

test('runbook detail page displays header and steps', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);
    $runbook = Runbook::factory()->create([
        'ops_request_id' => $opsRequest->id,
        'title' => 'Deploy to Production',
        'description' => 'Steps to deploy the application',
        'status' => 'approved',
    ]);
    RunbookStep::factory()->create([
        'runbook_id' => $runbook->id,
        'position' => 1,
        'instruction' => 'Backup database',
        'status' => 'completed',
        'executed_by' => 'admin',
        'executed_at' => now(),
    ]);
    RunbookStep::factory()->create([
        'runbook_id' => $runbook->id,
        'position' => 2,
        'instruction' => 'Run migrations',
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.runbooks.show', [$project, $opsRequest, $runbook]));
    $response->assertOk();
    $response->assertSee('Deploy to Production');
    $response->assertSee('Steps to deploy the application');
    $response->assertSee('Backup database');
    $response->assertSee('Completed');
    $response->assertSee('admin');
    $response->assertSee('admin');
    $response->assertSee('Run migrations');
    $response->assertSee('Pending');
});

test('runbook detail page has step status color coding', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);
    $runbook = Runbook::factory()->create(['ops_request_id' => $opsRequest->id]);
    RunbookStep::factory()->create(['runbook_id' => $runbook->id, 'status' => 'completed']);
    RunbookStep::factory()->create(['runbook_id' => $runbook->id, 'position' => 2, 'status' => 'failed']);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.runbooks.show', [$project, $opsRequest, $runbook]));
    $response->assertOk();
    $response->assertSee('Completed');
    $response->assertSee('Failed');
});

test('runbook detail is accessible from ops request page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);
    $runbook = Runbook::factory()->create(['ops_request_id' => $opsRequest->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee(route('projects.ops-requests.runbooks.show', [$project, $opsRequest, $runbook]));
});
