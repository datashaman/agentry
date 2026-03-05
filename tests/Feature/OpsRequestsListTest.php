<?php

use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('projects.ops-requests.index', $project));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the ops requests page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', $project));
    $response->assertOk();
});

test('ops requests page displays ops requests for the project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    OpsRequest::factory()->create(['project_id' => $project->id, 'title' => 'Deploy API']);
    OpsRequest::factory()->create(['project_id' => $project->id, 'title' => 'Migrate Database']);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', $project));
    $response->assertOk();
    $response->assertSee('Deploy API');
    $response->assertSee('Migrate Database');
});

test('ops requests page does not display ops requests from other projects', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $otherProject = Project::factory()->create(['organization_id' => $organization->id]);
    OpsRequest::factory()->create(['project_id' => $otherProject->id, 'title' => 'Secret Ops']);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', $project));
    $response->assertOk();
    $response->assertDontSee('Secret Ops');
});

test('ops requests page shows status, category, risk level, and execution type', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    OpsRequest::factory()->create([
        'project_id' => $project->id,
        'title' => 'Test Ops',
        'status' => 'triaged',
        'category' => 'deployment',
        'risk_level' => 'critical',
        'execution_type' => 'manual',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', $project));
    $response->assertOk();
    $response->assertSee('Test Ops');
    $response->assertSee('triaged');
    $response->assertSee('Deployment');
    $response->assertSee('Critical');
    $response->assertSee('Manual');
});

test('ops requests page filters by status', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    OpsRequest::factory()->create(['project_id' => $project->id, 'title' => 'New Request', 'status' => 'new']);
    OpsRequest::factory()->create(['project_id' => $project->id, 'title' => 'Triaged Request', 'status' => 'triaged']);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', [$project, 'status' => 'new']));
    $response->assertOk();
    $response->assertSee('New Request');
    $response->assertDontSee('Triaged Request');
});

test('ops requests page filters by category', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    OpsRequest::factory()->create(['project_id' => $project->id, 'title' => 'Deploy Thing', 'category' => 'deployment']);
    OpsRequest::factory()->create(['project_id' => $project->id, 'title' => 'Config Thing', 'category' => 'config']);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', [$project, 'category' => 'deployment']));
    $response->assertOk();
    $response->assertSee('Deploy Thing');
    $response->assertDontSee('Config Thing');
});

test('ops requests page filters by risk level', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    OpsRequest::factory()->create(['project_id' => $project->id, 'title' => 'High Risk', 'risk_level' => 'high']);
    OpsRequest::factory()->create(['project_id' => $project->id, 'title' => 'Low Risk', 'risk_level' => 'low']);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', [$project, 'riskLevel' => 'high']));
    $response->assertOk();
    $response->assertSee('High Risk');
    $response->assertDontSee('Low Risk');
});

test('ops requests page shows scheduled date', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    OpsRequest::factory()->create([
        'project_id' => $project->id,
        'title' => 'Scheduled Deploy',
        'scheduled_at' => '2026-06-15 10:00:00',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', $project));
    $response->assertOk();
    $response->assertSee('Jun 15, 2026');
});

test('ops requests page links to ops request detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', $project));
    $response->assertOk();
    $response->assertSee(route('projects.ops-requests.show', [$project, $opsRequest]));
});

test('ops requests page shows no ops requests message when none exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', $project));
    $response->assertOk();
    $response->assertSee('No Ops Requests');
});

test('ops requests page sorts by created_at descending by default', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    OpsRequest::factory()->create([
        'project_id' => $project->id,
        'title' => 'Older Request',
        'created_at' => '2026-01-01 00:00:00',
    ]);
    OpsRequest::factory()->create([
        'project_id' => $project->id,
        'title' => 'Newer Request',
        'created_at' => '2026-03-01 00:00:00',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.index', $project));
    $response->assertOk();
    $response->assertSeeInOrder(['Newer Request', 'Older Request']);
});
