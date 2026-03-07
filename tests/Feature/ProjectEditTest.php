<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('unauthenticated user cannot access project edit page', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.edit', $project))
        ->assertRedirect(route('login'));
});

test('project edit form displays with existing values', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'My App',
        'description' => 'A cool project',
        'instructions' => 'Use PHP 8.5',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.edit', $project));
    $response->assertOk();
    $response->assertSee('Edit Project');

    Livewire::test('pages::projects.edit', ['project' => $project])
        ->assertSet('name', 'My App')
        ->assertSet('description', 'A cool project')
        ->assertSet('instructions', 'Use PHP 8.5');
});

test('project edit updates the project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Old Name',
        'description' => 'Old description',
        'instructions' => 'Old instructions',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.edit', ['project' => $project])
        ->set('name', 'New Name')
        ->set('description', 'New description')
        ->set('instructions', 'New instructions')
        ->call('updateProject')
        ->assertRedirect(route('projects.show', $project));

    $project->refresh();
    expect($project->name)->toBe('New Name');
    expect($project->description)->toBe('New description');
    expect($project->instructions)->toBe('New instructions');
});

test('project edit validates required name', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.edit', ['project' => $project])
        ->set('name', '')
        ->call('updateProject')
        ->assertHasErrors(['name']);
});

test('project edit can clear description and instructions', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'description' => 'Has description',
        'instructions' => 'Has instructions',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.edit', ['project' => $project])
        ->set('description', '')
        ->set('instructions', '')
        ->call('updateProject')
        ->assertRedirect();

    $project->refresh();
    expect($project->description)->toBeNull();
    expect($project->instructions)->toBeNull();
});

test('type labels section renders when provider configured', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['project_key' => 'PROJ', 'type_labels' => ['Bug', 'Story']],
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.edit', ['project' => $project])
        ->assertSet('typeLabels', 'Bug, Story')
        ->assertSee('Type Labels');
});

test('type labels saved to project config', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['project_key' => 'owner/repo'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.edit', ['project' => $project])
        ->set('typeLabels', 'bug, enhancement, feature')
        ->call('updateProject')
        ->assertRedirect();

    $project->refresh();
    expect($project->work_item_provider_config['type_labels'])->toBe(['bug', 'enhancement', 'feature']);
});

test('clearing type labels removes them from config', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['project_key' => 'PROJ', 'type_labels' => ['Bug', 'Story']],
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.edit', ['project' => $project])
        ->set('typeLabels', '')
        ->call('updateProject')
        ->assertRedirect();

    $project->refresh();
    expect($project->work_item_provider_config)->not->toHaveKey('type_labels');
});

test('project show page has edit button', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Edit');
});
