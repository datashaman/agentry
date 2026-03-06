<?php

use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('unauthenticated user cannot access agent create page', function () {
    $this->get(route('agents.create'))
        ->assertRedirect(route('login'));
});

test('agent create form displays and creates an agent', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id, 'name' => 'Dev Team']);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id, 'name' => 'Code Writer', 'slug' => 'code-writer']);

    $this->actingAs($user);

    $response = $this->get(route('agents.create'));
    $response->assertOk();
    $response->assertSee('New Agent');

    Livewire::test('pages::agents.create')
        ->set('name', 'Test Agent')
        ->set('agent_role_id', (string) $agentRole->id)
        ->set('team_id', (string) $team->id)
        ->set('model', 'claude-opus-4-6')
        ->set('provider', 'anthropic')
        ->set('confidence_threshold', '0.85')
        ->call('createAgent')
        ->assertRedirect();

    $this->assertDatabaseHas('agents', [
        'name' => 'Test Agent',
        'model' => 'claude-opus-4-6',
        'provider' => 'anthropic',
        'status' => 'idle',
    ]);

    $agent = Agent::where('name', 'Test Agent')->first();
    expect($agent->confidence_threshold)->toBe(0.85)
        ->and($agent->agent_role_id)->toBe($agentRole->id)
        ->and($agent->team_id)->toBe($team->id);
});

test('agent create validates required fields', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::agents.create')
        ->set('name', '')
        ->set('agent_role_id', '')
        ->set('team_id', '')
        ->set('model', '')
        ->set('provider', '')
        ->set('confidence_threshold', '')
        ->call('createAgent')
        ->assertHasErrors(['name', 'agent_role_id', 'team_id', 'model', 'provider', 'confidence_threshold']);
});

test('agent create pre-fills agent role when passed as query param', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id, 'name' => 'PreSelected Type']);

    $this->actingAs($user);

    $response = $this->get(route('agents.create', ['agent_role' => $agentRole->id]));
    $response->assertOk();
    $response->assertSee('PreSelected Type');
});

test('unauthenticated user cannot access agent edit page', function () {
    $agent = Agent::factory()->create();

    $this->get(route('agents.edit', $agent))
        ->assertRedirect(route('login'));
});

test('agent create with overrides saves temperature max_steps max_tokens timeout', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::agents.create')
        ->set('name', 'Override Agent')
        ->set('agent_role_id', (string) $agentRole->id)
        ->set('team_id', (string) $team->id)
        ->set('model', 'claude-sonnet-4')
        ->set('provider', 'anthropic')
        ->set('temperature', '0.7')
        ->set('max_steps', '15')
        ->set('max_tokens', '8192')
        ->set('timeout', '120')
        ->call('createAgent')
        ->assertRedirect();

    $agent = Agent::where('name', 'Override Agent')->first();
    expect($agent)->not->toBeNull()
        ->and($agent->temperature)->toBe(0.7)
        ->and($agent->max_steps)->toBe(15)
        ->and($agent->max_tokens)->toBe(8192)
        ->and($agent->timeout)->toBe(120);
});

test('agent edit form displays pre-populated values and updates', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id, 'name' => 'Writer']);
    $agent = Agent::factory()->create([
        'team_id' => $team->id,
        'agent_role_id' => $agentRole->id,
        'name' => 'Old Name',
        'model' => 'claude-sonnet-4-6',
        'provider' => 'anthropic',
        'confidence_threshold' => 0.75,
        'status' => 'idle',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agents.edit', $agent));
    $response->assertOk();
    $response->assertSee('Edit Agent');

    Livewire::test('pages::agents.edit', ['agent' => $agent])
        ->assertSet('name', 'Old Name')
        ->assertSet('model', 'claude-sonnet-4-6')
        ->assertSet('confidence_threshold', '0.75')
        ->set('name', 'New Name')
        ->set('model', 'claude-opus-4-6')
        ->set('confidence_threshold', '0.9')
        ->call('updateAgent')
        ->assertRedirect();

    $this->assertDatabaseHas('agents', [
        'id' => $agent->id,
        'name' => 'New Name',
        'model' => 'claude-opus-4-6',
    ]);

    expect($agent->fresh()->confidence_threshold)->toBe(0.9)
        ->and($agent->fresh()->status)->toBe('idle');
});

test('agent delete removes agent and redirects to teams', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'To Delete']);

    $this->actingAs($user);

    Livewire::test('pages::agents.show', ['agent' => $agent])
        ->call('deleteAgent')
        ->assertRedirect(route('teams.index'));

    $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
});

test('teams page has create agent button', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee(route('agents.create'))
        ->assertSee('New Agent');
});
