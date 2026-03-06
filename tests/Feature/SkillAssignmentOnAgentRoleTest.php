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

    Livewire::test('pages::agent-roles.edit', ['agentRole' => $agentRole])
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

    Livewire::test('pages::agent-roles.edit', ['agentRole' => $agentRole])
        ->call('detachSkill', $skill->id);

    $agentRole->refresh();
    expect($agentRole->skills()->count())->toBe(0);
});
