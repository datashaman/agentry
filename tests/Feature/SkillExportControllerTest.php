<?php

use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;

test('export returns SKILL.md download', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'slug' => 'export-test',
        'description' => 'Export test skill.',
        'content' => 'Export body.',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('skills.export', $skill));
    $response->assertOk();
    $response->assertHeader('content-type', 'text/markdown; charset=utf-8');
    $response->assertHeader('content-disposition');

    $content = $response->streamedContent();
    expect($content)->toContain('name: export-test')
        ->and($content)->toContain('Export body.');
});

test('export returns 403 for wrong organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $user = User::factory()->withOrganization($orgA)->create(['current_organization_id' => $orgA->id]);
    $skill = Skill::factory()->create(['organization_id' => $orgB->id]);

    $this->actingAs($user);

    $response = $this->get(route('skills.export', $skill));
    $response->assertForbidden();
});
