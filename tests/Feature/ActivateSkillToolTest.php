<?php

use App\Agents\Tools\ActivateSkillTool;
use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Skill;

test('activate_skill returns skill content', function () {
    $organization = Organization::factory()->create();
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'slug' => 'laravel',
        'content' => 'Use Eloquent and Blade.',
    ]);
    $agentRole->skills()->attach($skill->id, ['position' => 0]);

    $tool = new ActivateSkillTool;
    $result = $tool->execute($skill->id, $organization, $agentRole);

    expect($result)->toContain('<skill_content')
        ->and($result)->toContain('name="laravel"')
        ->and($result)->toContain('Use Eloquent and Blade.');
});

test('activate_skill rejects skill from wrong organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $agentRole = AgentRole::factory()->create(['organization_id' => $orgA->id]);
    $skill = Skill::factory()->create(['organization_id' => $orgB->id]);

    $tool = new ActivateSkillTool;
    $result = $tool->execute($skill->id, $orgA, $agentRole);

    expect($result)->toContain('<error>');
});

test('activate_skill rejects skill not assigned to role', function () {
    $organization = Organization::factory()->create();
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);

    $tool = new ActivateSkillTool;
    $result = $tool->execute($skill->id, $organization, $agentRole);

    expect($result)->toContain('<error>');
});

test('activate_skill prevents duplicate activation', function () {
    $organization = Organization::factory()->create();
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'content' => 'Content.',
    ]);
    $agentRole->skills()->attach($skill->id, ['position' => 0]);

    $tool = new ActivateSkillTool;
    $tool->execute($skill->id, $organization, $agentRole);
    $result = $tool->execute($skill->id, $organization, $agentRole);

    expect($result)->toContain('already_activated="true"');
});

test('activate_skill includes resource list for imported skills', function () {
    $organization = Organization::factory()->create();
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'content' => 'Imported skill.',
        'resource_paths' => ['.agents/skills/test/scripts/setup.sh'],
    ]);
    $agentRole->skills()->attach($skill->id, ['position' => 0]);

    $tool = new ActivateSkillTool;
    $result = $tool->execute($skill->id, $organization, $agentRole);

    expect($result)->toContain('<available_resources>')
        ->and($result)->toContain('.agents/skills/test/scripts/setup.sh');
});
