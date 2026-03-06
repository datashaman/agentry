<?php

use App\Models\Agent;
use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('escalations.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the escalations page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
});

test('escalations page displays unresolved escalations for the user organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'trigger_type' => 'confidence',
        'reason' => 'Low confidence on plan',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('Low confidence on plan');
    $response->assertSee('Confidence');
});

test('escalations page does not display resolved escalations', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->resolved()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'reason' => 'Already resolved issue',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertDontSee('Already resolved issue');
});

test('escalations page does not display escalations from other organizations', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $otherProject = Project::factory()->create(['organization_id' => $otherOrganization->id]);
    $otherOpsRequest = OpsRequest::factory()->create(['project_id' => $otherProject->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $otherOpsRequest->id,
        'work_item_type' => OpsRequest::class,
        'reason' => 'Secret escalation',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertDontSee('Secret escalation');
});

test('escalations page shows escalations for ops requests', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'trigger_type' => 'policy',
        'reason' => 'Policy violation on deploy',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('Policy violation on deploy');
    $response->assertSee('Ops Request');
});

test('escalations page can filter by trigger type', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'trigger_type' => 'confidence',
        'reason' => 'Confidence issue here',
    ]);

    HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'trigger_type' => 'risk',
        'reason' => 'Risk issue here',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index', ['triggerType' => 'confidence']));
    $response->assertOk();
    $response->assertSee('Confidence issue here');
    $response->assertDontSee('Risk issue here');
});

test('escalations page displays agent name', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Reviewer Bot']);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'raised_by_agent_id' => $agent->id,
        'reason' => 'Agent raised this',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('Reviewer Bot');
});

test('escalations page shows work item links', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id, 'title' => 'Linked Ops Request Title']);

    HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'reason' => 'Needs review',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('Linked Ops Request Title');
    $response->assertSee(route('projects.ops-requests.show', [$project, $opsRequest]));
});

test('escalations page shows empty state when no escalations', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('No Escalations');
});
