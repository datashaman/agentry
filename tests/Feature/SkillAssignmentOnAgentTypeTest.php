<?php

use App\Models\AgentType;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use Livewire\Livewire;

test('agent type detail shows assigned skills section', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentType = AgentType::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('agent-types.show', $agentType));
    $response->assertOk();
    $response->assertSee('Assigned Skills');
});

test('can attach skill to agent type', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentType = AgentType::factory()->create(['organization_id' => $organization->id]);
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Laravel',
        'slug' => 'laravel',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::agent-types.show', ['agentType' => $agentType])
        ->set('selectedSkillId', (string) $skill->id)
        ->call('attachSkill');

    $agentType->refresh();
    $agentType->load('skills');
    expect($agentType->skills()->count())->toBe(1);
    expect($agentType->skills->first()->name)->toBe('Laravel');
});

test('can detach skill from agent type', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentType = AgentType::factory()->create(['organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    $agentType->skills()->attach($skill->id, ['position' => 0]);

    $this->actingAs($user);

    Livewire::test('pages::agent-types.show', ['agentType' => $agentType])
        ->call('detachSkill', $skill->id);

    $agentType->refresh();
    expect($agentType->skills()->count())->toBe(0);
});

test('can reorder skills on agent type', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentType = AgentType::factory()->create(['organization_id' => $organization->id]);
    $skillA = Skill::factory()->create(['organization_id' => $organization->id, 'name' => 'A']);
    $skillB = Skill::factory()->create(['organization_id' => $organization->id, 'name' => 'B']);
    $skillC = Skill::factory()->create(['organization_id' => $organization->id, 'name' => 'C']);
    $agentType->skills()->attach($skillA->id, ['position' => 0]);
    $agentType->skills()->attach($skillB->id, ['position' => 1]);
    $agentType->skills()->attach($skillC->id, ['position' => 2]);

    $this->actingAs($user);

    Livewire::test('pages::agent-types.show', ['agentType' => $agentType])
        ->call('moveSkillDown', $skillA->id);

    $agentType->refresh();
    $order = $agentType->skills()->orderByPivot('position')->pluck('name')->toArray();
    expect($order)->toBe(['B', 'A', 'C']);
});
