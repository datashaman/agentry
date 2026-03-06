<?php

use App\Models\Agent;
use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('ops request detail shows resolve and defer actions for unresolved escalation', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->forOpsRequest($opsRequest)->create([
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'confidence',
        'agent_confidence' => 0.45,
        'reason' => 'Low confidence in plan',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee('Resolve');
    $response->assertSee('Defer');
    $response->assertSee('Confidence: 45%');
});

test('user can resolve an escalation on an ops request', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'risk',
        'reason' => 'High risk deployment',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.ops-requests.show', ['project' => $project, 'opsRequest' => $opsRequest])
        ->call('startResolving', $escalation->id)
        ->set('resolutionNotes', 'Approved after manual review')
        ->call('resolveEscalation', $escalation->id)
        ->assertHasNoErrors();

    $escalation->refresh();
    expect($escalation->resolution)->toBe('Approved after manual review');
    expect($escalation->resolved_by)->toBe($user->name);
    expect($escalation->resolved_at)->not->toBeNull();
});

test('user can defer an escalation on an ops request', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'raised_by_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.ops-requests.show', ['project' => $project, 'opsRequest' => $opsRequest])
        ->call('deferEscalation', $escalation->id)
        ->assertHasNoErrors();

    $escalation->refresh();
    expect($escalation->resolution)->toBe('Deferred');
    expect($escalation->resolved_at)->not->toBeNull();
});

test('resolving an escalation requires resolution notes', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'raised_by_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.ops-requests.show', ['project' => $project, 'opsRequest' => $opsRequest])
        ->call('startResolving', $escalation->id)
        ->set('resolutionNotes', '')
        ->call('resolveEscalation', $escalation->id)
        ->assertHasErrors(['resolutionNotes']);

    $escalation->refresh();
    expect($escalation->resolved_at)->toBeNull();
});

test('resolved escalation shows resolution details', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->resolved()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'policy',
        'reason' => 'Policy violation detected',
        'resolution' => 'Approved after review',
        'resolved_by' => 'Jane Doe',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee('Approved after review');
    $response->assertSee('Resolved by Jane Doe');
});

test('cancel resolving resets state', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'raised_by_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.ops-requests.show', ['project' => $project, 'opsRequest' => $opsRequest])
        ->call('startResolving', $escalation->id)
        ->assertSet('resolvingEscalationId', $escalation->id)
        ->call('cancelResolving')
        ->assertSet('resolvingEscalationId', null)
        ->assertSet('resolutionNotes', '');
});
