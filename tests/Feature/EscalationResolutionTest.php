<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Epic;
use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('story detail shows resolve and defer actions for unresolved escalation', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    HitlEscalation::factory()->forStory($story)->create([
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'confidence',
        'agent_confidence' => 0.45,
        'reason' => 'Low confidence in spec',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Resolve');
    $response->assertSee('Defer');
    $response->assertSee('Confidence: 45%');
});

test('user can resolve an escalation on a story', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $escalation = HitlEscalation::factory()->forStory($story)->create([
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'confidence',
        'reason' => 'Low confidence in spec',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('startResolving', $escalation->id)
        ->assertSet('resolvingEscalationId', $escalation->id)
        ->set('resolutionNotes', 'Reviewed and approved the spec')
        ->call('resolveEscalation', $escalation->id)
        ->assertHasNoErrors()
        ->assertSet('resolvingEscalationId', null)
        ->assertSet('resolutionNotes', '');

    $escalation->refresh();
    expect($escalation->resolution)->toBe('Reviewed and approved the spec');
    expect($escalation->resolved_by)->toBe($user->name);
    expect($escalation->resolved_at)->not->toBeNull();
});

test('user can defer an escalation on a story', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $escalation = HitlEscalation::factory()->forStory($story)->create([
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'risk',
        'reason' => 'High risk deployment',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('deferEscalation', $escalation->id)
        ->assertHasNoErrors();

    $escalation->refresh();
    expect($escalation->resolution)->toBe('Deferred');
    expect($escalation->resolved_by)->toBe($user->name);
    expect($escalation->resolved_at)->not->toBeNull();
});

test('resolving an escalation requires resolution notes', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $escalation = HitlEscalation::factory()->forStory($story)->create([
        'raised_by_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
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
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    HitlEscalation::factory()->forStory($story)->resolved()->create([
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'policy',
        'reason' => 'Policy violation detected',
        'resolution' => 'Approved after review',
        'resolved_by' => 'Jane Doe',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Approved after review');
    $response->assertSee('Resolved by Jane Doe');
});

test('user can resolve an escalation on a bug', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'ambiguity',
        'reason' => 'Ambiguous repro steps',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.bugs.show', ['project' => $project, 'bug' => $bug])
        ->call('startResolving', $escalation->id)
        ->set('resolutionNotes', 'Clarified repro steps with reporter')
        ->call('resolveEscalation', $escalation->id)
        ->assertHasNoErrors();

    $escalation->refresh();
    expect($escalation->resolution)->toBe('Clarified repro steps with reporter');
    expect($escalation->resolved_by)->toBe($user->name);
    expect($escalation->resolved_at)->not->toBeNull();
});

test('user can defer an escalation on a bug', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'raised_by_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.bugs.show', ['project' => $project, 'bug' => $bug])
        ->call('deferEscalation', $escalation->id)
        ->assertHasNoErrors();

    $escalation->refresh();
    expect($escalation->resolution)->toBe('Deferred');
    expect($escalation->resolved_at)->not->toBeNull();
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

test('cancel resolving resets state', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $escalation = HitlEscalation::factory()->forStory($story)->create([
        'raised_by_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('startResolving', $escalation->id)
        ->assertSet('resolvingEscalationId', $escalation->id)
        ->call('cancelResolving')
        ->assertSet('resolvingEscalationId', null)
        ->assertSet('resolutionNotes', '');
});
