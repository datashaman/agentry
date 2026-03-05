<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\User;

test('repos list displays project repos with all columns', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'name' => 'my-test-repo',
        'url' => 'https://github.com/example/my-test-repo.git',
        'primary_language' => 'PHP',
        'default_branch' => 'main',
        'tags' => ['backend', 'api'],
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.index', $project));
    $response->assertOk();
    $response->assertSee('my-test-repo');
    $response->assertSee('https://github.com/example/my-test-repo.git');
    $response->assertSee('PHP');
    $response->assertSee('main');
    $response->assertSee('backend');
    $response->assertSee('api');
});

test('repos list shows empty state when no repos exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.index', $project));
    $response->assertOk();
    $response->assertSee('No Repositories');
});

test('repos list has link to repo detail page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.index', $project));
    $response->assertOk();
    $response->assertSee(route('projects.repos.show', [$project, $repo]));
});

test('repos list has link to create new repo', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.index', $project));
    $response->assertOk();
    $response->assertSee(route('projects.repos.create', $project));
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
