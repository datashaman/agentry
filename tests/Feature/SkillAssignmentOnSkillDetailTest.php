<?php

use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use Livewire\Livewire;

test('skill detail shows add agent role form when agent roles available', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    AgentRole::factory()->create(['organization_id' => $organization->id, 'name' => 'Coding Agent']);

    $this->actingAs($user);

    $response = $this->get(route('skills.show', $skill));
    $response->assertOk();
    $response->assertSee('Add agent role');
});

test('can attach agent role from skill detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Coding Agent',
        'slug' => 'coding-agent',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::skills.show', ['skill' => $skill])
        ->set('selectedAgentRoleId', (string) $agentRole->id)
        ->call('attachAgentRole');

    $skill->refresh();
    $skill->load('agentRoles');
    expect($skill->agentRoles()->count())->toBe(1);
    expect($skill->agentRoles->first()->name)->toBe('Coding Agent');
});

test('can detach agent role from skill detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    $skill->agentRoles()->attach($agentRole->id, ['position' => 0]);

    $this->actingAs($user);

    Livewire::test('pages::skills.show', ['skill' => $skill])
        ->call('detachAgentRole', $agentRole->id);

    $skill->refresh();
    expect($skill->agentRoles()->count())->toBe(0);
});

test('assigning from skill detail syncs with agent role view', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::skills.show', ['skill' => $skill])
        ->set('selectedAgentRoleId', (string) $agentRole->id)
        ->call('attachAgentRole');

    $agentRole->refresh();
    $agentRole->load('skills');
    expect($agentRole->skills()->count())->toBe(1);
    expect($agentRole->skills->first()->id)->toBe($skill->id);
});
