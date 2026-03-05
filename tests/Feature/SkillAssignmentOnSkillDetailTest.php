<?php

use App\Models\AgentType;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use Livewire\Livewire;

test('skill detail shows add agent type form when agent types available', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    AgentType::factory()->create(['organization_id' => $organization->id, 'name' => 'Coding Agent']);

    $this->actingAs($user);

    $response = $this->get(route('skills.show', $skill));
    $response->assertOk();
    $response->assertSee('Add agent type');
});

test('can attach agent type from skill detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    $agentType = AgentType::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Coding Agent',
        'slug' => 'coding-agent',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::skills.show', ['skill' => $skill])
        ->set('selectedAgentTypeId', (string) $agentType->id)
        ->call('attachAgentType');

    $skill->refresh();
    $skill->load('agentTypes');
    expect($skill->agentTypes()->count())->toBe(1);
    expect($skill->agentTypes->first()->name)->toBe('Coding Agent');
});

test('can detach agent type from skill detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    $agentType = AgentType::factory()->create(['organization_id' => $organization->id]);
    $skill->agentTypes()->attach($agentType->id, ['position' => 0]);

    $this->actingAs($user);

    Livewire::test('pages::skills.show', ['skill' => $skill])
        ->call('detachAgentType', $agentType->id);

    $skill->refresh();
    expect($skill->agentTypes()->count())->toBe(0);
});

test('assigning from skill detail syncs with agent type view', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    $agentType = AgentType::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::skills.show', ['skill' => $skill])
        ->set('selectedAgentTypeId', (string) $agentType->id)
        ->call('attachAgentType');

    $agentType->refresh();
    $agentType->load('skills');
    expect($agentType->skills()->count())->toBe(1);
    expect($agentType->skills->first()->id)->toBe($skill->id);
});
