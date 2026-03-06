<?php

use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;

test('skills list page displays skills with agent role count', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);

    Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Laravel Development',
        'slug' => 'laravel-development',
        'description' => 'Laravel best practices',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('skills.index'));
    $response->assertOk();
    $response->assertSee('Laravel Development');
    $response->assertSee('laravel-development');
    $response->assertSee('Laravel best practices');
});

test('skills list shows only skills from current organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $user = User::factory()->withOrganization($orgA)->create(['current_organization_id' => $orgA->id]);

    Skill::factory()->create([
        'organization_id' => $orgA->id,
        'name' => 'Org A Skill',
        'slug' => 'org-a-skill',
    ]);
    Skill::factory()->create([
        'organization_id' => $orgB->id,
        'name' => 'Org B Skill',
        'slug' => 'org-b-skill',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('skills.index'));
    $response->assertOk();
    $response->assertSee('Org A Skill');
    $response->assertDontSee('Org B Skill');
});

test('skills list page shows empty state when no skills exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('skills.index'));
    $response->assertOk();
    $response->assertSee('No Skills');
});

test('skills list has create button and skill links', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Test Skill',
        'slug' => 'test-skill',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('skills.index'));
    $response->assertOk();
    $response->assertSee('New Skill');
    $response->assertSee(route('skills.create'));
    $response->assertSee(route('skills.show', $skill));
});
