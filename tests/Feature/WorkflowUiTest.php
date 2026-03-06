<?php

use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('team create form shows workflow type select', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get(route('teams.create'))
        ->assertOk()
        ->assertSee('Workflow Type')
        ->assertSee('None (independent agents)')
        ->assertSee('Chain (sequential)');
});

test('creating team saves workflow type', function () {
    $user = User::factory()->withOrganization()->create();

    Livewire::actingAs($user)
        ->test('pages::teams.create')
        ->set('name', 'Workflow Team')
        ->set('workflow_type', 'chain')
        ->call('createTeam');

    $team = Team::where('name', 'Workflow Team')->first();
    expect($team)->not->toBeNull()
        ->and($team->workflow_type)->toBe('chain');
});

test('creating team with invalid workflow type fails validation', function () {
    $user = User::factory()->withOrganization()->create();

    Livewire::actingAs($user)
        ->test('pages::teams.create')
        ->set('name', 'Bad Workflow Team')
        ->set('workflow_type', 'invalid_type')
        ->call('createTeam')
        ->assertHasErrors(['workflow_type']);
});

test('team edit form shows workflow config fields for chain type', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();
    $team = Team::factory()->create([
        'organization_id' => $org->id,
        'workflow_type' => 'chain',
        'workflow_config' => ['agents' => [], 'cumulative' => false],
    ]);

    $this->actingAs($user)
        ->get(route('teams.edit', $team))
        ->assertOk()
        ->assertSee('Agent Execution Order')
        ->assertSee('Cumulative Mode');
});

test('team edit form shows workflow config fields for router type', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();
    $team = Team::factory()->create([
        'organization_id' => $org->id,
        'workflow_type' => 'router',
        'workflow_config' => ['router_agent_id' => null, 'agents' => []],
    ]);

    $this->actingAs($user)
        ->get(route('teams.edit', $team))
        ->assertOk()
        ->assertSee('Router Agent')
        ->assertSee('Routable Agents');
});

test('team edit form shows workflow config fields for evaluator_optimizer type', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();
    $team = Team::factory()->create([
        'organization_id' => $org->id,
        'workflow_type' => 'evaluator_optimizer',
        'workflow_config' => [
            'generator_agent_id' => null,
            'evaluator_agent_id' => null,
            'max_refinements' => 3,
            'min_rating' => 'good',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('teams.edit', $team))
        ->assertOk()
        ->assertSee('Generator Agent')
        ->assertSee('Evaluator Agent')
        ->assertSee('Max Refinements')
        ->assertSee('Minimum Rating');
});

test('updating team saves chain workflow config', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();
    $team = Team::factory()->create(['organization_id' => $org->id]);
    $agentRole = AgentRole::factory()->forOrganization($org)->create();
    $agent1 = Agent::factory()->create(['team_id' => $team->id, 'agent_role_id' => $agentRole->id, 'name' => 'Agent A']);
    $agent2 = Agent::factory()->create(['team_id' => $team->id, 'agent_role_id' => $agentRole->id, 'name' => 'Agent B']);

    Livewire::actingAs($user)
        ->test('pages::teams.edit', ['team' => $team])
        ->set('workflow_type', 'chain')
        ->set('workflow_agent_ids', [(string) $agent1->id, (string) $agent2->id])
        ->set('cumulative', '1')
        ->call('updateTeam');

    $team->refresh();
    expect($team->workflow_type)->toBe('chain')
        ->and($team->workflow_config['agents'])->toBe([$agent1->id, $agent2->id])
        ->and($team->workflow_config['cumulative'])->toBeTrue();
});

test('updating team saves orchestrator workflow config', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();
    $team = Team::factory()->create(['organization_id' => $org->id]);
    $agentRole = AgentRole::factory()->forOrganization($org)->create();
    $planner = Agent::factory()->create(['team_id' => $team->id, 'agent_role_id' => $agentRole->id, 'name' => 'Planner']);
    $worker = Agent::factory()->create(['team_id' => $team->id, 'agent_role_id' => $agentRole->id, 'name' => 'Worker']);

    Livewire::actingAs($user)
        ->test('pages::teams.edit', ['team' => $team])
        ->set('workflow_type', 'orchestrator')
        ->set('planner_agent_id', (string) $planner->id)
        ->set('workflow_agent_ids', [(string) $worker->id])
        ->set('max_iterations', '5')
        ->call('updateTeam');

    $team->refresh();
    expect($team->workflow_type)->toBe('orchestrator')
        ->and($team->workflow_config['planner_agent_id'])->toBe($planner->id)
        ->and($team->workflow_config['agents'])->toBe([$worker->id])
        ->and($team->workflow_config['max_iterations'])->toBe(5);
});

test('team show page displays workflow type badge', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();
    $team = Team::factory()->create([
        'organization_id' => $org->id,
        'workflow_type' => 'chain',
        'workflow_config' => ['agents' => [], 'cumulative' => false],
    ]);

    $this->actingAs($user)
        ->get(route('teams.show', $team))
        ->assertOk()
        ->assertSee('Workflow')
        ->assertSee('Chain');
});

test('team show page displays workflow config details', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();
    $team = Team::factory()->create(['organization_id' => $org->id]);
    $agentRole = AgentRole::factory()->forOrganization($org)->create();
    $agent = Agent::factory()->create(['team_id' => $team->id, 'agent_role_id' => $agentRole->id, 'name' => 'My Agent']);

    $team->update([
        'workflow_type' => 'chain',
        'workflow_config' => ['agents' => [$agent->id], 'cumulative' => true],
    ]);

    $this->actingAs($user)
        ->get(route('teams.show', $team))
        ->assertOk()
        ->assertSee('Execution Order:')
        ->assertSee('My Agent')
        ->assertSee('Cumulative:')
        ->assertSee('Yes');
});

test('team show page displays none workflow without config details', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();
    $team = Team::factory()->create(['organization_id' => $org->id, 'workflow_type' => 'none']);

    $this->actingAs($user)
        ->get(route('teams.show', $team))
        ->assertOk()
        ->assertSee('None')
        ->assertDontSee('Execution Order:');
});
