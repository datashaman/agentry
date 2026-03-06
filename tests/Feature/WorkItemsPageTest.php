<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from work items page', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('projects.work-items.index', $project));

    $response->assertRedirect(route('login'));
});

test('work items page loads for authenticated user', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $response = $this->actingAs($user)->get(route('projects.work-items.index', $project));

    $response->assertOk();
    $response->assertSee('Work Items');
});

test('work items page shows setup prompt when no provider configured', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => null,
    ]);

    $response = $this->actingAs($user)->get(route('projects.work-items.index', $project));

    $response->assertOk();
    $response->assertSee('No Provider Configured');
    $response->assertSee('Configure Provider');
});

test('work items page shows provider name when configured', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['project_key' => 'PROJ'],
    ]);

    $response = $this->actingAs($user)->get(route('projects.work-items.index', $project));

    $response->assertOk();
    $response->assertSee('Jira');
});

test('work items page shows error when project key is missing', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'github',
        'work_item_provider_config' => [],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.work-items.index', ['project' => $project])
        ->call('loadWorkItems')
        ->assertSet('error', 'No project key configured. Edit the project to set a project key (e.g. owner/repo for GitHub).');
});

test('work items page shows error when github app not installed', function () {
    $organization = Organization::factory()->create(['github_installation_id' => null]);
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['project_key' => 'owner/repo'],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.work-items.index', ['project' => $project])
        ->call('loadWorkItems')
        ->assertSet('error', 'No GitHub App installed for this organization. Install the GitHub App first.');
});

test('project edit page shows work item provider fields', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $response = $this->actingAs($user)->get(route('projects.edit', $project));

    $response->assertOk();
    $response->assertSee('Work Item Provider');
    $response->assertSee('Project Key');
});
