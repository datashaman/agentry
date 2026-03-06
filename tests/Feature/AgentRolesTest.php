<?php

use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('agent roles list page displays agent roles with counts', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);

    $agentRole = AgentRole::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Code Writer',
        'slug' => 'code-writer',
        'description' => 'Writes production code',
    ]);

    Agent::factory()->count(3)->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agent-roles.index'));
    $response->assertOk();
    $response->assertSee('Code Writer');
    $response->assertSee('code-writer');
    $response->assertSee('3');
});

test('agent roles list shows only agent roles from current organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $user = User::factory()->withOrganization($orgA)->create(['current_organization_id' => $orgA->id]);

    AgentRole::factory()->create(['organization_id' => $orgA->id, 'name' => 'Org A Type', 'slug' => 'org-a-type']);
    AgentRole::factory()->create(['organization_id' => $orgB->id, 'name' => 'Org B Type', 'slug' => 'org-b-type']);

    $this->actingAs($user);

    $response = $this->get(route('agent-roles.index'));
    $response->assertOk();
    $response->assertSee('Org A Type');
    $response->assertDontSee('Org B Type');
});

test('agent roles list page shows empty state when no agent roles exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('agent-roles.index'));
    $response->assertOk();
    $response->assertSee('No Agent Roles');
});

test('agent role detail page shows description, instructions, tools, and agents', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);

    $agentRole = AgentRole::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Reviewer',
        'slug' => 'reviewer',
        'description' => 'Reviews pull requests',
        'instructions' => 'You are a code reviewer.',
        'tools' => ['code_review', 'testing'],
    ]);

    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
        'name' => 'Review Bot Alpha',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agent-roles.show', $agentRole));
    $response->assertOk();
    $response->assertSee('Reviewer');
    $response->assertSee('Reviews pull requests');
    $response->assertSee('code_review');
    $response->assertSee('testing');
    $response->assertSee('Review Bot Alpha');
});

test('create agent role form displays and creates an agent role', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('agent-roles.create'));
    $response->assertOk();
    $response->assertSee('New Agent Role');

    Livewire::test('pages::agent-roles.create')
        ->set('name', 'Planner')
        ->set('slug', 'planner')
        ->set('description', 'Plans sprints')
        ->set('instructions', 'You plan sprints.')
        ->set('tools', 'planning, estimation')
        ->call('createAgentRole')
        ->assertRedirect();

    $agentRole = AgentRole::where('organization_id', $organization->id)
        ->where('slug', 'planner')
        ->first();

    expect($agentRole)->not->toBeNull()
        ->and($agentRole->name)->toBe('Planner')
        ->and($agentRole->description)->toBe('Plans sprints')
        ->and($agentRole->instructions)->toBe('You plan sprints.')
        ->and($agentRole->tools)->toBe(['planning', 'estimation']);
});

test('create agent role with default config values', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.create')
        ->set('name', 'Coder')
        ->set('slug', 'coder')
        ->set('default_model', 'claude-sonnet-4')
        ->set('default_provider', 'anthropic')
        ->set('default_temperature', '0.7')
        ->set('default_max_steps', '10')
        ->set('default_max_tokens', '4096')
        ->set('default_timeout', '120')
        ->call('createAgentRole')
        ->assertRedirect();

    $agentRole = AgentRole::where('organization_id', $organization->id)->where('slug', 'coder')->first();

    expect($agentRole)->not->toBeNull()
        ->and($agentRole->default_model)->toBe('claude-sonnet-4')
        ->and($agentRole->default_provider)->toBe('anthropic')
        ->and($agentRole->default_temperature)->toBe(0.7)
        ->and($agentRole->default_max_steps)->toBe(10)
        ->and($agentRole->default_max_tokens)->toBe(4096)
        ->and($agentRole->default_timeout)->toBe(120);
});

test('create agent role validates required fields', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.create')
        ->set('name', '')
        ->set('slug', '')
        ->call('createAgentRole')
        ->assertHasErrors(['name', 'slug']);
});

test('create agent role validates slug uniqueness', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    AgentRole::factory()->create(['organization_id' => $organization->id, 'slug' => 'existing-slug']);

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.create')
        ->set('name', 'New Type')
        ->set('slug', 'existing-slug')
        ->call('createAgentRole')
        ->assertHasErrors(['slug']);
});

test('edit agent role form displays pre-populated values and updates', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $agentRole = AgentRole::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Old Name',
        'slug' => 'old-name',
        'description' => 'Old description',
        'instructions' => 'Old instructions',
        'tools' => ['cap1', 'cap2'],
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agent-roles.edit', $agentRole));
    $response->assertOk();
    $response->assertSee('Edit Agent Role');

    Livewire::test('pages::agent-roles.edit', ['agentRole' => $agentRole])
        ->assertSet('name', 'Old Name')
        ->assertSet('slug', 'old-name')
        ->assertSet('description', 'Old description')
        ->assertSet('instructions', 'Old instructions')
        ->assertSet('tools', 'cap1, cap2')
        ->set('name', 'New Name')
        ->set('slug', 'new-name')
        ->call('updateAgentRole')
        ->assertRedirect();

    $this->assertDatabaseHas('agent_roles', [
        'id' => $agentRole->id,
        'name' => 'New Name',
        'slug' => 'new-name',
    ]);
});

test('delete agent role removes it when no agents assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.show', ['agentRole' => $agentRole])
        ->call('deleteAgentRole')
        ->assertRedirect(route('agent-roles.index'));

    $this->assertDatabaseMissing('agent_roles', ['id' => $agentRole->id]);
});

test('delete agent role is prevented when agents are assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);

    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.show', ['agentRole' => $agentRole])
        ->call('deleteAgentRole')
        ->assertNoRedirect();

    $this->assertDatabaseHas('agent_roles', ['id' => $agentRole->id]);
});
