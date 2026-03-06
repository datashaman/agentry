<?php

use App\Models\AgentRole;
use App\Models\EventResponder;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

test('agent role show page displays event responders section', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'ops_request',
        'status' => 'planning',
        'instructions' => 'Critique this spec',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agent-roles.show', $agentRole));
    $response->assertOk();
    $response->assertSee('Event Responders');
    $response->assertSee('Critique this spec');
    $response->assertSee('planning');
});

test('add event responder creates a responder on the agent role', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.edit', ['agentRole' => $agentRole])
        ->set('responderWorkItemType', 'ops_request')
        ->set('responderStatus', 'in_progress')
        ->set('responderInstructions', 'Implement the feature')
        ->call('addEventResponder')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('event_responders', [
        'agent_role_id' => $agentRole->id,
        'work_item_type' => 'ops_request',
        'status' => 'in_progress',
        'instructions' => 'Implement the feature',
    ]);
});

test('add event responder validates required fields', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.edit', ['agentRole' => $agentRole])
        ->set('responderWorkItemType', '')
        ->set('responderStatus', '')
        ->set('responderInstructions', '')
        ->call('addEventResponder')
        ->assertHasErrors(['responderWorkItemType', 'responderStatus', 'responderInstructions']);
});

test('add event responder prevents duplicate work_item_type and status', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'ops_request',
        'status' => 'planning',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.edit', ['agentRole' => $agentRole])
        ->set('responderWorkItemType', 'ops_request')
        ->set('responderStatus', 'planning')
        ->set('responderInstructions', 'Duplicate attempt')
        ->call('addEventResponder')
        ->assertHasErrors('responderStatus');

    expect(EventResponder::where('agent_role_id', $agentRole->id)->count())->toBe(1);
});

test('remove event responder deletes the responder', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    $responder = EventResponder::factory()->forAgentRole($agentRole)->create();

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.edit', ['agentRole' => $agentRole])
        ->call('removeEventResponder', $responder->id);

    $this->assertDatabaseMissing('event_responders', ['id' => $responder->id]);
});

test('changing work item type resets status selection', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.edit', ['agentRole' => $agentRole])
        ->set('responderWorkItemType', 'ops_request')
        ->set('responderStatus', 'planning')
        ->set('responderWorkItemType', '')
        ->assertSet('responderStatus', '');
});
