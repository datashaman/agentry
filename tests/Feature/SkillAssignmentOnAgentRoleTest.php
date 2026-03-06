<?php

use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use Livewire\Livewire;

test('agent role detail shows assigned skills section', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('agent-roles.show', $agentRole));
    $response->assertOk();
    $response->assertSee('Assigned Skills');
});

test('can attach skill to agent role', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Laravel',
        'slug' => 'laravel',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.show', ['agentRole' => $agentRole])
        ->set('selectedSkillId', (string) $skill->id)
        ->call('attachSkill');

    $agentRole->refresh();
    $agentRole->load('skills');
    expect($agentRole->skills()->count())->toBe(1);
    expect($agentRole->skills->first()->name)->toBe('Laravel');
});

test('can detach skill from agent role', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    $agentRole->skills()->attach($skill->id, ['position' => 0]);

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.show', ['agentRole' => $agentRole])
        ->call('detachSkill', $skill->id);

    $agentRole->refresh();
    expect($agentRole->skills()->count())->toBe(0);
});

test('can reorder skills on agent role', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    $skillA = Skill::factory()->create(['organization_id' => $organization->id, 'name' => 'A']);
    $skillB = Skill::factory()->create(['organization_id' => $organization->id, 'name' => 'B']);
    $skillC = Skill::factory()->create(['organization_id' => $organization->id, 'name' => 'C']);
    $agentRole->skills()->attach($skillA->id, ['position' => 0]);
    $agentRole->skills()->attach($skillB->id, ['position' => 1]);
    $agentRole->skills()->attach($skillC->id, ['position' => 2]);

    $this->actingAs($user);

    Livewire::test('pages::agent-roles.show', ['agentRole' => $agentRole])
        ->call('moveSkillDown', $skillA->id);

    $agentRole->refresh();
    $order = $agentRole->skills()->orderByPivot('position')->pluck('name')->toArray();
    expect($order)->toBe(['B', 'A', 'C']);
});
