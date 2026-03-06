<?php

use App\Contracts\WorkItemProvider;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\WorkItemProviderManager;
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
        ->call('loadIssues')
        ->assertSet('error', 'No project key configured. Edit the project to set a project key (e.g. owner/repo for GitHub).');
});

test('tracking an issue creates a work item record', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['project_key' => 'owner/repo'],
    ]);

    $fakeProvider = Mockery::mock(WorkItemProvider::class);
    $fakeProvider->allows('listIssues')->andReturn([
        [
            'key' => '#42',
            'title' => 'Fix the widget',
            'type' => 'bug',
            'status' => 'open',
            'priority' => null,
            'assignee' => 'dev1',
            'url' => 'https://github.com/owner/repo/issues/42',
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-01-01T00:00:00Z',
        ],
    ]);

    $fakeManager = Mockery::mock(WorkItemProviderManager::class);
    $fakeManager->allows('resolve')->andReturn($fakeProvider);
    $this->app->instance(WorkItemProviderManager::class, $fakeManager);

    $this->actingAs($user);

    Livewire::test('pages::projects.work-items.index', ['project' => $project])
        ->call('loadIssues')
        ->assertSee('Fix the widget')
        ->call('trackIssue', '#42');

    expect($project->workItems()->where('provider_key', '#42')->exists())->toBeTrue();
});

test('untracking an issue deletes the work item record', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['project_key' => 'owner/repo'],
    ]);

    WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'github',
        'provider_key' => '#42',
        'title' => 'Fix the widget',
        'url' => 'https://github.com/owner/repo/issues/42',
    ]);

    $fakeProvider = Mockery::mock(WorkItemProvider::class);
    $fakeProvider->allows('listIssues')->andReturn([
        [
            'key' => '#42',
            'title' => 'Fix the widget',
            'type' => 'bug',
            'status' => 'open',
            'priority' => null,
            'assignee' => null,
            'url' => 'https://github.com/owner/repo/issues/42',
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-01-01T00:00:00Z',
        ],
    ]);

    $fakeManager = Mockery::mock(WorkItemProviderManager::class);
    $fakeManager->allows('resolve')->andReturn($fakeProvider);
    $this->app->instance(WorkItemProviderManager::class, $fakeManager);

    $this->actingAs($user);

    Livewire::test('pages::projects.work-items.index', ['project' => $project])
        ->call('loadIssues')
        ->call('untrackIssue', '#42');

    expect($project->workItems()->where('provider_key', '#42')->exists())->toBeFalse();
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
