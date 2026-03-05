<?php

use App\Models\AgentType;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use Livewire\Livewire;

test('skill detail page shows name, slug, description, content', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Laravel',
        'slug' => 'laravel',
        'description' => 'Laravel best practices',
        'content' => 'Use Eloquent and Blade.',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('skills.show', $skill));
    $response->assertOk();
    $response->assertSee('Laravel');
    $response->assertSee('laravel');
    $response->assertSee('Laravel best practices');
    $response->assertSee('Use Eloquent and Blade.');
});

test('skill detail page shows edit and delete buttons', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('skills.show', $skill));
    $response->assertOk();
    $response->assertSee(route('skills.edit', $skill));
    $response->assertSee('Delete');
});

test('create skill form displays and creates a skill', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('skills.create'));
    $response->assertOk();
    $response->assertSee('Create Skill');

    Livewire::test('pages::skills.create')
        ->set('name', 'Flux UI')
        ->set('slug', 'flux-ui')
        ->set('description', 'Flux UI components')
        ->set('content', 'Use flux: components.')
        ->call('createSkill')
        ->assertRedirect(route('skills.index'));

    $this->assertDatabaseHas('skills', [
        'organization_id' => $organization->id,
        'name' => 'Flux UI',
        'slug' => 'flux-ui',
        'description' => 'Flux UI components',
        'content' => 'Use flux: components.',
    ]);
});

test('edit skill form updates a skill', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Old Name',
        'slug' => 'old-name',
        'content' => 'Old content.',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::skills.edit', ['skill' => $skill])
        ->set('name', 'New Name')
        ->set('slug', 'new-name')
        ->set('content', 'New content.')
        ->call('updateSkill')
        ->assertRedirect(route('skills.show', $skill));

    $skill->refresh();
    expect($skill->name)->toBe('New Name')
        ->and($skill->slug)->toBe('new-name')
        ->and($skill->content)->toBe('New content.');
});

test('delete skill works when no agent types assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::skills.show', ['skill' => $skill])
        ->call('deleteSkill')
        ->assertRedirect(route('skills.index'));

    $this->assertDatabaseMissing('skills', ['id' => $skill->id]);
});

test('delete skill is prevented when agent types assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);
    $skill = Skill::factory()->create(['organization_id' => $organization->id]);
    $agentType = AgentType::factory()->create(['organization_id' => $organization->id]);
    $skill->agentTypes()->attach($agentType->id, ['position' => 0]);

    $this->actingAs($user);

    Livewire::test('pages::skills.show', ['skill' => $skill])
        ->call('deleteSkill');

    $this->assertDatabaseHas('skills', ['id' => $skill->id]);
});

test('skill from another organization returns 403', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $user = User::factory()->withOrganization($orgA)->create(['current_organization_id' => $orgA->id]);
    $skill = Skill::factory()->create(['organization_id' => $orgB->id]);

    $this->actingAs($user);

    $response = $this->get(route('skills.show', $skill));
    $response->assertForbidden();
});
