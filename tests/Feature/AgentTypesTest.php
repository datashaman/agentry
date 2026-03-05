<?php

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('agent types list page displays agent types with counts', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);

    $agentType = AgentType::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Code Writer',
        'slug' => 'code-writer',
        'description' => 'Writes production code',
    ]);

    Agent::factory()->count(3)->create([
        'agent_type_id' => $agentType->id,
        'team_id' => $team->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agent-types.index'));
    $response->assertOk();
    $response->assertSee('Code Writer');
    $response->assertSee('code-writer');
    $response->assertSee('3');
});

test('agent types list shows only agent types from current organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $user = User::factory()->withOrganization($orgA)->create(['current_organization_id' => $orgA->id]);

    AgentType::factory()->create(['organization_id' => $orgA->id, 'name' => 'Org A Type', 'slug' => 'org-a-type']);
    AgentType::factory()->create(['organization_id' => $orgB->id, 'name' => 'Org B Type', 'slug' => 'org-b-type']);

    $this->actingAs($user);

    $response = $this->get(route('agent-types.index'));
    $response->assertOk();
    $response->assertSee('Org A Type');
    $response->assertDontSee('Org B Type');
});

test('agent types list page shows empty state when no agent types exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('agent-types.index'));
    $response->assertOk();
    $response->assertSee('No Agent Types');
});

test('agent type detail page shows description, instructions, tools, and agents', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);

    $agentType = AgentType::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Reviewer',
        'slug' => 'reviewer',
        'description' => 'Reviews pull requests',
        'instructions' => 'You are a code reviewer.',
        'tools' => ['code_review', 'testing'],
    ]);

    $agent = Agent::factory()->create([
        'agent_type_id' => $agentType->id,
        'team_id' => $team->id,
        'name' => 'Review Bot Alpha',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agent-types.show', $agentType));
    $response->assertOk();
    $response->assertSee('Reviewer');
    $response->assertSee('Reviews pull requests');
    $response->assertSee('code_review');
    $response->assertSee('testing');
    $response->assertSee('Review Bot Alpha');
});

test('create agent type form displays and creates an agent type', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('agent-types.create'));
    $response->assertOk();
    $response->assertSee('New Agent Type');

    Livewire::test('pages::agent-types.create')
        ->set('name', 'Planner')
        ->set('slug', 'planner')
        ->set('description', 'Plans sprints')
        ->set('instructions', 'You plan sprints.')
        ->set('tools', 'planning, estimation')
        ->call('createAgentType')
        ->assertRedirect();

    $agentType = AgentType::where('organization_id', $organization->id)
        ->where('slug', 'planner')
        ->first();

    expect($agentType)->not->toBeNull()
        ->and($agentType->name)->toBe('Planner')
        ->and($agentType->description)->toBe('Plans sprints')
        ->and($agentType->instructions)->toBe('You plan sprints.')
        ->and($agentType->tools)->toBe(['planning', 'estimation']);
});

test('create agent type validates required fields', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    Livewire::test('pages::agent-types.create')
        ->set('name', '')
        ->set('slug', '')
        ->call('createAgentType')
        ->assertHasErrors(['name', 'slug']);
});

test('create agent type validates slug uniqueness', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    AgentType::factory()->create(['organization_id' => $organization->id, 'slug' => 'existing-slug']);

    $this->actingAs($user);

    Livewire::test('pages::agent-types.create')
        ->set('name', 'New Type')
        ->set('slug', 'existing-slug')
        ->call('createAgentType')
        ->assertHasErrors(['slug']);
});

test('edit agent type form displays pre-populated values and updates', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $agentType = AgentType::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Old Name',
        'slug' => 'old-name',
        'description' => 'Old description',
        'instructions' => 'Old instructions',
        'tools' => ['cap1', 'cap2'],
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agent-types.edit', $agentType));
    $response->assertOk();
    $response->assertSee('Edit Agent Type');

    Livewire::test('pages::agent-types.edit', ['agentType' => $agentType])
        ->assertSet('name', 'Old Name')
        ->assertSet('slug', 'old-name')
        ->assertSet('description', 'Old description')
        ->assertSet('instructions', 'Old instructions')
        ->assertSet('tools', 'cap1, cap2')
        ->set('name', 'New Name')
        ->set('slug', 'new-name')
        ->call('updateAgentType')
        ->assertRedirect();

    $this->assertDatabaseHas('agent_types', [
        'id' => $agentType->id,
        'name' => 'New Name',
        'slug' => 'new-name',
    ]);
});

test('delete agent type removes it when no agents assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $agentType = AgentType::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::agent-types.show', ['agentType' => $agentType])
        ->call('deleteAgentType')
        ->assertRedirect(route('agent-types.index'));

    $this->assertDatabaseMissing('agent_types', ['id' => $agentType->id]);
});

test('delete agent type is prevented when agents are assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);

    $agentType = AgentType::factory()->create(['organization_id' => $organization->id]);
    Agent::factory()->create([
        'agent_type_id' => $agentType->id,
        'team_id' => $team->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::agent-types.show', ['agentType' => $agentType])
        ->call('deleteAgentType')
        ->assertNoRedirect();

    $this->assertDatabaseHas('agent_types', ['id' => $agentType->id]);
});
