<?php

use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;

test('unauthenticated user cannot access teams page', function () {
    $this->get(route('teams.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can access teams page', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('Teams & Agents');
});

test('teams page displays teams for user organization', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();

    $team1 = Team::factory()->create(['organization_id' => $org->id, 'name' => 'Alpha Team']);
    $team2 = Team::factory()->create(['organization_id' => $org->id, 'name' => 'Beta Team']);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('Alpha Team')
        ->assertSee('Beta Team');
});

test('teams page does not display teams from other organizations', function () {
    $user = User::factory()->withOrganization()->create();
    $otherOrg = Organization::factory()->create();

    Team::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Other Org Team']);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertDontSee('Other Org Team');
});

test('teams page shows agent count per team', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();

    $team = Team::factory()->create(['organization_id' => $org->id, 'name' => 'Dev Team']);
    Agent::factory()->count(3)->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('Dev Team')
        ->assertSee('3 agents');
});

test('teams page displays agent details', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();

    $team = Team::factory()->create(['organization_id' => $org->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $org->id, 'name' => 'Developer']);
    $agent = Agent::factory()->create([
        'team_id' => $team->id,
        'agent_role_id' => $agentRole->id,
        'name' => 'Claude Agent',
        'model' => 'claude-opus-4-6',
        'status' => 'active',
        'confidence_threshold' => 0.85,
    ]);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('Claude Agent')
        ->assertSee('Developer')
        ->assertSee('claude-opus-4-6')
        ->assertSee('Active')
        ->assertSee('85%');
});

test('teams page shows empty state when no teams', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('No Teams');
});

test('teams page shows empty agent message when team has no agents', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();

    Team::factory()->create(['organization_id' => $org->id, 'name' => 'Empty Team']);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('Empty Team')
        ->assertSee('0 agents')
        ->assertSee('No agents in this team.');
});

test('teams page displays agent status with color coding', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();

    $team = Team::factory()->create(['organization_id' => $org->id]);
    Agent::factory()->create(['team_id' => $team->id, 'status' => 'idle']);
    Agent::factory()->create(['team_id' => $team->id, 'status' => 'active']);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('Idle')
        ->assertSee('Active');
});

test('teams navigation link appears in sidebar', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Teams');
});

test('teams page shows singular agent for single agent', function () {
    $user = User::factory()->withOrganization()->create();
    $org = $user->currentOrganization();

    $team = Team::factory()->create(['organization_id' => $org->id]);
    Agent::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('1 agent');
});
