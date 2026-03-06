<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('unauthenticated user cannot access project create page', function () {
    $this->get(route('projects.create'))
        ->assertRedirect(route('login'));
});

test('project create form displays and creates a project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('projects.create'));
    $response->assertOk();
    $response->assertSee('New Project');

    Livewire::test('pages::projects.create')
        ->set('name', 'My App')
        ->set('slug', 'my-app')
        ->call('createProject')
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'name' => 'My App',
        'organization_id' => $organization->id,
    ]);

    $project = Project::where('name', 'My App')->first();
    expect($project->slug)->toBe('my-app');
    expect($project->description)->toBeNull();
    expect($project->instructions)->toBeNull();
});

test('project create with description and instructions', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    Livewire::test('pages::projects.create')
        ->set('name', 'My App')
        ->set('slug', 'my-app')
        ->set('description', 'A cool project')
        ->set('instructions', 'Always use PHP 8.5 features')
        ->call('createProject')
        ->assertRedirect();

    $project = Project::where('name', 'My App')->first();
    expect($project->description)->toBe('A cool project');
    expect($project->instructions)->toBe('Always use PHP 8.5 features');
});

test('project create validates required name', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    Livewire::test('pages::projects.create')
        ->set('name', '')
        ->call('createProject')
        ->assertHasErrors(['name']);
});

test('project create validates unique slug within organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    Project::factory()->create([
        'organization_id' => $organization->id,
        'slug' => 'my-app',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.create')
        ->set('name', 'My App')
        ->set('slug', 'my-app')
        ->call('createProject')
        ->assertHasErrors(['slug']);
});

test('project create allows same slug in different organization', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user = User::factory()->withOrganization($org2)->create();

    Project::factory()->create([
        'organization_id' => $org1->id,
        'slug' => 'my-app',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.create')
        ->set('name', 'My App')
        ->set('slug', 'my-app')
        ->call('createProject')
        ->assertRedirect();

    expect(Project::where('organization_id', $org2->id)->where('slug', 'my-app')->exists())->toBeTrue();
});

test('projects index shows new project button', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('New Project');
});
