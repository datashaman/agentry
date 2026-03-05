<?php

use App\Models\Branch;
use App\Models\Bug;
use App\Models\Epic;
use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Story;
use App\Models\User;

test('branches list displays repo branches with all columns', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id, 'name' => 'my-repo']);
    Branch::factory()->create([
        'repo_id' => $repo->id,
        'name' => 'feature/login-flow',
        'base_branch' => 'main',
        'work_item_id' => null,
        'work_item_type' => null,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.branches.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('feature/login-flow');
    $response->assertSee('main');
    $response->assertDontSee('No Branches');
});

test('branches list shows empty state when no branches exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.branches.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('No Branches');
});

test('branches list displays linked story with correct link', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Add login page']);
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    Branch::factory()->forStory($story)->create(['repo_id' => $repo->id, 'name' => 'feature/login']);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.branches.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('Add login page');
    $response->assertSee('Story');
    $response->assertSee(route('projects.stories.show', [$project, $story]));
});

test('branches list displays linked bug with correct link', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id, 'title' => 'Fix crash on startup']);
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    Branch::factory()->forBug($bug)->create(['repo_id' => $repo->id, 'name' => 'fix/crash']);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.branches.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('Fix crash on startup');
    $response->assertSee('Bug');
    $response->assertSee(route('projects.bugs.show', [$project, $bug]));
});

test('branches list displays linked ops request with correct link', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id, 'title' => 'Deploy to staging']);
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    Branch::factory()->forOpsRequest($opsRequest)->create(['repo_id' => $repo->id, 'name' => 'ops/deploy']);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.branches.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('Deploy to staging');
    $response->assertSee('OpsRequest');
    $response->assertSee(route('projects.ops-requests.show', [$project, $opsRequest]));
});

test('branches list is accessible from repo detail page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.show', [$project, $repo]));
    $response->assertOk();
    $response->assertSee(route('projects.repos.branches.index', [$project, $repo]));
});
