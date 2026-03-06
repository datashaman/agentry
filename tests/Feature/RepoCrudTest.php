<?php

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Project;
use App\Models\PullRequest;
use App\Models\Repo;
use App\Models\User;
use App\Models\Worktree;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('repo detail page shows name, url, language, branch, tags', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'name' => 'detail-repo',
        'url' => 'https://github.com/example/detail-repo.git',
        'primary_language' => 'TypeScript',
        'default_branch' => 'develop',
        'tags' => ['frontend', 'ui'],
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.show', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('detail-repo');
    $response->assertSee('https://github.com/example/detail-repo.git');
    $response->assertSee('TypeScript');
    $response->assertSee('develop');
    $response->assertSee('frontend');
    $response->assertSee('ui');
});

test('repo detail page shows counts of branches, worktrees, and pull requests', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    Branch::factory()->count(3)->create(['repo_id' => $repo->id]);
    Worktree::factory()->count(2)->create(['repo_id' => $repo->id]);
    PullRequest::factory()->count(4)->create(['repo_id' => $repo->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.show', [$project, $repo]));
    $response->assertOk();
    $response->assertSeeInOrder(['Branches', '3']);
    $response->assertSeeInOrder(['Worktrees', '2']);
    $response->assertSeeInOrder(['Pull Requests', '4']);
});

test('repo detail page has edit and delete buttons', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.show', [$project, $repo]));
    $response->assertOk();
    $response->assertSee(route('projects.repos.edit', [$project, $repo]));
    $response->assertSee('Delete');
});

test('create repo form displays and creates a repo', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.create', $project));
    $response->assertOk();
    $response->assertSee('Create Repository');

    Livewire::test('pages::projects.repos.create', ['project' => $project])
        ->set('name', 'new-repo')
        ->set('url', 'https://github.com/example/new-repo.git')
        ->set('primary_language', 'PHP')
        ->set('default_branch', 'main')
        ->set('tags', 'backend, api')
        ->call('createRepo')
        ->assertRedirect();

    $this->assertDatabaseHas('repos', [
        'project_id' => $project->id,
        'name' => 'new-repo',
        'url' => 'https://github.com/example/new-repo.git',
        'primary_language' => 'PHP',
        'default_branch' => 'main',
    ]);

    $repo = Repo::where('name', 'new-repo')->first();
    expect($repo->tags)->toBe(['backend', 'api']);
});

test('create repo validates required fields', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.repos.create', ['project' => $project])
        ->set('name', '')
        ->set('url', '')
        ->call('createRepo')
        ->assertHasErrors(['name', 'url']);
});

test('edit repo form displays pre-populated values and updates a repo', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'name' => 'old-name',
        'url' => 'https://github.com/example/old-name.git',
        'primary_language' => 'PHP',
        'default_branch' => 'main',
        'tags' => ['backend'],
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.edit', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('Edit Repository');

    Livewire::test('pages::projects.repos.edit', ['project' => $project, 'repo' => $repo])
        ->assertSet('name', 'old-name')
        ->assertSet('url', 'https://github.com/example/old-name.git')
        ->assertSet('primary_language', 'PHP')
        ->assertSet('default_branch', 'main')
        ->assertSet('tags', 'backend')
        ->set('name', 'new-name')
        ->set('url', 'https://github.com/example/new-name.git')
        ->set('tags', 'frontend, ui')
        ->call('updateRepo')
        ->assertRedirect();

    $this->assertDatabaseHas('repos', [
        'id' => $repo->id,
        'name' => 'new-name',
        'url' => 'https://github.com/example/new-name.git',
    ]);

    $repo->refresh();
    expect($repo->tags)->toBe(['frontend', 'ui']);
});

test('link repo creates a local repo from github data', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create([
        'github_id' => '12345',
        'github_token' => 'fake-token',
        'github_nickname' => 'testuser',
    ]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            [
                'id' => 42,
                'name' => 'linkable-repo',
                'full_name' => 'testuser/linkable-repo',
                'html_url' => 'https://github.com/testuser/linkable-repo',
                'description' => 'A repo to link',
                'language' => 'PHP',
                'default_branch' => 'main',
                'private' => false,
            ],
        ]),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.repos.index', ['project' => $project])
        ->call('linkRepo', 42);

    $this->assertDatabaseHas('repos', [
        'project_id' => $project->id,
        'name' => 'linkable-repo',
        'url' => 'https://github.com/testuser/linkable-repo',
        'primary_language' => 'PHP',
        'default_branch' => 'main',
    ]);
});

test('unlink repo removes the local repo', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create([
        'github_id' => '12345',
        'github_token' => 'fake-token',
        'github_nickname' => 'testuser',
    ]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    Repo::factory()->create([
        'project_id' => $project->id,
        'name' => 'linked-repo',
        'url' => 'https://github.com/testuser/linked-repo',
    ]);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            [
                'id' => 99,
                'name' => 'linked-repo',
                'full_name' => 'testuser/linked-repo',
                'html_url' => 'https://github.com/testuser/linked-repo',
                'description' => null,
                'language' => 'PHP',
                'default_branch' => 'main',
                'private' => false,
            ],
        ]),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.repos.index', ['project' => $project])
        ->call('unlinkRepo', 99);

    $this->assertDatabaseMissing('repos', [
        'project_id' => $project->id,
        'url' => 'https://github.com/testuser/linked-repo',
    ]);
});

test('delete repo removes the repo and redirects to index', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.repos.show', ['project' => $project, 'repo' => $repo])
        ->call('deleteRepo')
        ->assertRedirect(route('projects.repos.index', $project));

    $this->assertDatabaseMissing('repos', ['id' => $repo->id]);
});
