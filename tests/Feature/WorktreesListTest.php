<?php

use App\Models\Branch;
use App\Models\Bug;
use App\Models\Epic;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Story;
use App\Models\User;
use App\Models\Worktree;

test('worktrees list displays repo worktrees with all columns', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id, 'name' => 'my-repo']);
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    Worktree::factory()->create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'path' => '/worktrees/feature-x',
        'status' => 'active',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.worktrees.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('/worktrees/feature-x');
    $response->assertSee('Active');
    $response->assertDontSee('No Worktrees');
});

test('worktrees list shows empty state when no worktrees exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.worktrees.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('No Worktrees');
});

test('worktrees list displays status color coding', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);

    Worktree::factory()->create(['repo_id' => $repo->id, 'branch_id' => $branch->id, 'path' => '/wt/active', 'status' => 'active']);
    Worktree::factory()->interrupted()->create(['repo_id' => $repo->id, 'branch_id' => $branch->id, 'path' => '/wt/interrupted']);
    Worktree::factory()->stale()->create(['repo_id' => $repo->id, 'branch_id' => $branch->id, 'path' => '/wt/stale']);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.worktrees.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('Active');
    $response->assertSee('Interrupted');
    $response->assertSee('Stale');
});

test('worktrees list displays linked story with correct link', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Add login']);
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    Worktree::factory()->forStory($story)->create(['repo_id' => $repo->id, 'branch_id' => $branch->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.worktrees.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('Add login');
    $response->assertSee('Story');
    $response->assertSee(route('projects.stories.show', [$project, $story]));
});

test('worktrees list displays linked bug with correct link', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id, 'title' => 'Fix crash']);
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    Worktree::factory()->forBug($bug)->create(['repo_id' => $repo->id, 'branch_id' => $branch->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.worktrees.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('Fix crash');
    $response->assertSee('Bug');
    $response->assertSee(route('projects.bugs.show', [$project, $bug]));
});

test('worktrees list displays interrupted at and interrupted reason', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    Worktree::factory()->interrupted('Agent timeout')->create(['repo_id' => $repo->id, 'branch_id' => $branch->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.worktrees.index', [$project, $repo]));
    $response->assertOk();
    $response->assertSee('Agent timeout');
});

test('worktrees list is accessible from repo detail page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.repos.show', [$project, $repo]));
    $response->assertOk();
    $response->assertSee(route('projects.repos.worktrees.index', [$project, $repo]));
});
