<?php

use App\Models\Agent;
use App\Models\Branch;
use App\Models\ChangeSet;
use App\Models\Epic;
use App\Models\Organization;
use App\Models\Project;
use App\Models\PullRequest;
use App\Models\Repo;
use App\Models\Review;
use App\Models\Story;
use App\Models\User;

test('story detail displays change set with PRs and reviews', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $agent = Agent::factory()->create();
    $changeSet = ChangeSet::factory()->forStory($story)->create(['summary' => 'Implement login flow']);
    $pr = PullRequest::factory()->create([
        'change_set_id' => $changeSet->id,
        'branch_id' => $branch->id,
        'repo_id' => $repo->id,
        'agent_id' => $agent->id,
        'title' => 'Add login form',
        'status' => 'open',
        'external_url' => 'https://github.com/example/pull/123',
    ]);
    Review::factory()->create([
        'pull_request_id' => $pr->id,
        'agent_id' => $agent->id,
        'status' => 'approved',
        'body' => 'Looks good to me',
        'submitted_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Implement login flow');
    $response->assertSee('Add login form');
    $response->assertSee('open');
    $response->assertSee($repo->name);
    $response->assertSee($branch->name);
    $response->assertSee($agent->name);
    $response->assertSee('https://github.com/example/pull/123');
    $response->assertSee('Looks good to me');
    $response->assertSee('approved');
});

test('bug detail displays change set detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = \App\Models\Bug::factory()->create(['project_id' => $project->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $changeSet = ChangeSet::factory()->forBug($bug)->create(['summary' => 'Fix memory leak']);
    PullRequest::factory()->create([
        'change_set_id' => $changeSet->id,
        'branch_id' => $branch->id,
        'repo_id' => $repo->id,
        'title' => 'Patch allocation',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('Fix memory leak');
    $response->assertSee('Patch allocation');
});

test('ops request detail displays change set detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = \App\Models\OpsRequest::factory()->create(['project_id' => $project->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $changeSet = ChangeSet::factory()->forOpsRequest($opsRequest)->create(['summary' => 'Deploy config changes']);
    PullRequest::factory()->create([
        'change_set_id' => $changeSet->id,
        'branch_id' => $branch->id,
        'repo_id' => $repo->id,
        'title' => 'Update env vars',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.ops-requests.show', [$project, $opsRequest]));
    $response->assertOk();
    $response->assertSee('Deploy config changes');
    $response->assertSee('Update env vars');
});
