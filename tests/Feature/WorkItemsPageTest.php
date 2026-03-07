<?php

use App\Contracts\WorkItemProvider;
use App\Events\WorkItemTracked;
use App\Events\WorkItemUntracked;
use App\Exceptions\GitHubTokenExpiredException;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\WorkItemProviderManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
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

test('tracking an issue creates a work item record and dispatches event', function () {
    Event::fake([WorkItemTracked::class]);

    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['project_key' => 'owner/repo'],
        'instructions' => 'You are a helpful assistant for this project.',
    ]);

    $issueData = [
        'key' => '#42',
        'title' => 'Fix the widget',
        'description' => 'The widget is broken and needs fixing.',
        'type' => 'bug',
        'status' => 'open',
        'priority' => null,
        'assignee' => 'dev1',
        'url' => 'https://github.com/owner/repo/issues/42',
        'created_at' => '2026-01-01T00:00:00Z',
        'updated_at' => '2026-01-01T00:00:00Z',
    ];

    $fakeProvider = Mockery::mock(WorkItemProvider::class);
    $fakeProvider->allows('listIssues')->andReturn([$issueData]);
    $fakeProvider->allows('getIssue')->andReturn($issueData);

    $fakeManager = Mockery::mock(WorkItemProviderManager::class);
    $fakeManager->allows('resolve')->andReturn($fakeProvider);
    $this->app->instance(WorkItemProviderManager::class, $fakeManager);

    $this->actingAs($user);

    Livewire::test('pages::projects.work-items.index', ['project' => $project])
        ->call('loadIssues')
        ->assertSee('Fix the widget')
        ->call('trackIssue', '#42');

    $workItem = $project->workItems()->where('provider_key', '#42')->first();
    expect($workItem)->not->toBeNull();
    expect($workItem->description)->toBe('The widget is broken and needs fixing.');
    $conversation = $workItem->latestConversation();
    expect($conversation)->not->toBeNull();
    $messages = $conversation->messages()->oldest()->get();
    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe('system');
    expect($messages[0]->content)->toBe($project->instructions);
    expect($messages[1]->role)->toBe('user');
    expect($messages[1]->content)->toContain('Fix the widget');
    expect($messages[1]->content)->toContain('The widget is broken and needs fixing.');

    Event::assertDispatched(WorkItemTracked::class, function (WorkItemTracked $event) use ($workItem) {
        return $event->workItem->is($workItem);
    });
});

test('untracking an issue deletes the work item record and dispatches event', function () {
    Event::fake([WorkItemUntracked::class]);

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
            'description' => null,
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

    Event::assertDispatched(WorkItemUntracked::class, function (WorkItemUntracked $event) use ($project) {
        return $event->project->is($project) && $event->providerKey === '#42';
    });
});

test('untracking an issue cleans up orphaned agent conversations', function () {
    Event::fake([WorkItemUntracked::class]);

    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['project_key' => 'owner/repo'],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'github',
        'provider_key' => '#99',
        'title' => 'Test cleanup',
        'url' => 'https://github.com/owner/repo/issues/99',
    ]);

    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Test conversation',
    ]);
    $workItem->agentConversations()->attach($conversation);

    AgentConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'agent' => 'anonymous',
        'role' => 'user',
        'content' => 'Hello',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    $fakeProvider = Mockery::mock(WorkItemProvider::class);
    $fakeProvider->allows('listIssues')->andReturn([
        [
            'key' => '#99',
            'title' => 'Test cleanup',
            'description' => null,
            'type' => 'bug',
            'status' => 'open',
            'priority' => null,
            'assignee' => null,
            'url' => 'https://github.com/owner/repo/issues/99',
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
        ->call('untrackIssue', '#99');

    expect(AgentConversation::find($conversation->id))->toBeNull();
    expect(AgentConversationMessage::where('conversation_id', $conversation->id)->exists())->toBeFalse();
});

test('shows reconnect button when GitHub token is expired', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['project_key' => 'owner/repo'],
    ]);

    $fakeProvider = Mockery::mock(WorkItemProvider::class);
    $fakeProvider->allows('listIssues')->andThrow(new GitHubTokenExpiredException);

    $fakeManager = Mockery::mock(WorkItemProviderManager::class);
    $fakeManager->allows('resolve')->andReturn($fakeProvider);
    $this->app->instance(WorkItemProviderManager::class, $fakeManager);

    $this->actingAs($user);

    Livewire::test('pages::projects.work-items.index', ['project' => $project])
        ->call('loadIssues')
        ->assertSet('tokenExpired', true)
        ->assertSee('Reconnect GitHub');
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
