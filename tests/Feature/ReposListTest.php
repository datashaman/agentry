<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('repos page shows github not connected state when user has no github', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.index', $project));
    $response->assertOk();
    $response->assertSee('GitHub Not Connected');
    $response->assertSee('Connect GitHub');
});

test('repos page fetches and displays github repos for personal org', function () {
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
                'id' => 1,
                'name' => 'my-laravel-app',
                'full_name' => 'testuser/my-laravel-app',
                'html_url' => 'https://github.com/testuser/my-laravel-app',
                'description' => 'A Laravel application',
                'language' => 'PHP',
                'default_branch' => 'main',
                'private' => false,
            ],
            [
                'id' => 2,
                'name' => 'vue-frontend',
                'full_name' => 'testuser/vue-frontend',
                'html_url' => 'https://github.com/testuser/vue-frontend',
                'description' => 'Vue.js frontend',
                'language' => 'JavaScript',
                'default_branch' => 'develop',
                'private' => true,
            ],
        ]),
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.index', $project));
    $response->assertOk();
    $response->assertSee('my-laravel-app');
    $response->assertSee('vue-frontend');
    $response->assertSee('PHP');
    $response->assertSee('JavaScript');
    $response->assertSee('Public');
    $response->assertSee('Private');
});

test('repos page fetches repos from github org when org has github_account_login', function () {
    $organization = Organization::factory()->create([
        'github_account_login' => 'acme-org',
    ]);
    $user = User::factory()->withOrganization($organization)->create([
        'github_id' => '12345',
        'github_token' => 'fake-token',
        'github_nickname' => 'testuser',
    ]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    Http::fake([
        'api.github.com/orgs/acme-org/repos*' => Http::response([
            [
                'id' => 10,
                'name' => 'org-repo',
                'full_name' => 'acme-org/org-repo',
                'html_url' => 'https://github.com/acme-org/org-repo',
                'description' => 'Org repository',
                'language' => 'Python',
                'default_branch' => 'main',
                'private' => false,
            ],
        ]),
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.index', $project));
    $response->assertOk();
    $response->assertSee('org-repo');
    $response->assertSee('Python');
});

test('repos page shows linked state for already linked repos', function () {
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
                'id' => 1,
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

    $response = $this->get(route('projects.repos.index', $project));
    $response->assertOk();
    $response->assertSee('linked-repo');
    $response->assertSee('Unlink');
});

test('repos page has link to add manually', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.index', $project));
    $response->assertOk();
    $response->assertSee(route('projects.repos.create', $project));
    $response->assertSee('Add Manually');
});

test('repos page shows no repos found when github returns empty', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create([
        'github_id' => '12345',
        'github_token' => 'fake-token',
        'github_nickname' => 'testuser',
    ]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([]),
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.index', $project));
    $response->assertOk();
    $response->assertSee('No Repositories Found');
});

test('repo detail placeholder page loads', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.show', [$project, $repo]));
    $response->assertOk();
    $response->assertSee($repo->name);
});
