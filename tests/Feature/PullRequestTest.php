<?php

use App\Models\Agent;
use App\Models\Branch;
use App\Models\ChangeSet;
use App\Models\PullRequest;
use App\Models\Repo;

test('can create a pull request', function () {
    $pullRequest = PullRequest::factory()->create();

    expect($pullRequest)->toBeInstanceOf(PullRequest::class)
        ->and($pullRequest->title)->not->toBeEmpty();
});

test('pull request belongs to change set', function () {
    $changeSet = ChangeSet::factory()->create();
    $pullRequest = PullRequest::factory()->create(['change_set_id' => $changeSet->id]);

    expect($pullRequest->changeSet)->toBeInstanceOf(ChangeSet::class)
        ->and($pullRequest->changeSet->id)->toBe($changeSet->id);
});

test('pull request belongs to branch', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $pullRequest = PullRequest::factory()->create([
        'branch_id' => $branch->id,
        'repo_id' => $repo->id,
    ]);

    expect($pullRequest->branch)->toBeInstanceOf(Branch::class)
        ->and($pullRequest->branch->id)->toBe($branch->id);
});

test('pull request belongs to repo', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $pullRequest = PullRequest::factory()->create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
    ]);

    expect($pullRequest->repo)->toBeInstanceOf(Repo::class)
        ->and($pullRequest->repo->id)->toBe($repo->id);
});

test('pull request belongs to agent (author)', function () {
    $agent = Agent::factory()->create();
    $pullRequest = PullRequest::factory()->create(['agent_id' => $agent->id]);

    expect($pullRequest->agent)->toBeInstanceOf(Agent::class)
        ->and($pullRequest->agent->id)->toBe($agent->id);
});

test('change set has many pull requests', function () {
    $changeSet = ChangeSet::factory()->create();
    PullRequest::factory()->count(3)->create(['change_set_id' => $changeSet->id]);

    expect($changeSet->pullRequests)->toHaveCount(3);
});

test('branch has many pull requests', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    PullRequest::factory()->count(2)->create([
        'branch_id' => $branch->id,
        'repo_id' => $repo->id,
    ]);

    expect($branch->pullRequests)->toHaveCount(2);
});

test('repo has many pull requests', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    PullRequest::factory()->count(2)->create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
    ]);

    expect($repo->pullRequests)->toHaveCount(2);
});

test('agent has many authored pull requests', function () {
    $agent = Agent::factory()->create();
    PullRequest::factory()->count(2)->create(['agent_id' => $agent->id]);

    expect($agent->authoredPullRequests)->toHaveCount(2);
});

test('pull request status defaults to open', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $changeSet = ChangeSet::factory()->create();

    $pullRequest = PullRequest::create([
        'change_set_id' => $changeSet->id,
        'branch_id' => $branch->id,
        'repo_id' => $repo->id,
        'title' => 'Test PR',
    ]);
    $pullRequest->refresh();

    expect($pullRequest->status)->toBe('open');
});

test('pull request supports all status values', function ($status) {
    $pullRequest = PullRequest::factory()->create(['status' => $status]);

    expect($pullRequest->status)->toBe($status);
})->with(['open', 'approved', 'merged', 'closed']);

test('pull request description is nullable', function () {
    $pullRequest = PullRequest::factory()->create(['description' => null]);

    expect($pullRequest->description)->toBeNull();
});

test('pull request external_id is nullable', function () {
    $pullRequest = PullRequest::factory()->create(['external_id' => null]);

    expect($pullRequest->external_id)->toBeNull();
});

test('pull request external_url is nullable', function () {
    $pullRequest = PullRequest::factory()->create(['external_url' => null]);

    expect($pullRequest->external_url)->toBeNull();
});

test('pull request agent_id is nullable', function () {
    $pullRequest = PullRequest::factory()->create(['agent_id' => null]);

    expect($pullRequest->agent_id)->toBeNull()
        ->and($pullRequest->agent)->toBeNull();
});

test('can update a pull request', function () {
    $pullRequest = PullRequest::factory()->create(['status' => 'open']);

    $pullRequest->update(['status' => 'approved', 'title' => 'Updated title']);
    $pullRequest->refresh();

    expect($pullRequest->status)->toBe('approved')
        ->and($pullRequest->title)->toBe('Updated title');
});

test('can delete a pull request', function () {
    $pullRequest = PullRequest::factory()->create();
    $id = $pullRequest->id;

    $pullRequest->delete();

    expect(PullRequest::find($id))->toBeNull();
});

test('can list pull requests', function () {
    PullRequest::factory()->count(5)->create();

    expect(PullRequest::all())->toHaveCount(5);
});

test('deleting change set cascades to pull requests', function () {
    $changeSet = ChangeSet::factory()->create();
    PullRequest::factory()->count(2)->create(['change_set_id' => $changeSet->id]);

    $changeSet->delete();

    expect(PullRequest::count())->toBe(0);
});

test('deleting branch cascades to pull requests', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    PullRequest::factory()->count(2)->create([
        'branch_id' => $branch->id,
        'repo_id' => $repo->id,
    ]);

    $branch->delete();

    expect(PullRequest::count())->toBe(0);
});

test('deleting repo cascades to pull requests', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    PullRequest::factory()->count(2)->create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
    ]);

    $repo->delete();

    expect(PullRequest::count())->toBe(0);
});

test('deleting agent nullifies pull request agent_id', function () {
    $agent = Agent::factory()->create();
    $pullRequest = PullRequest::factory()->create(['agent_id' => $agent->id]);

    $agent->delete();
    $pullRequest->refresh();

    expect($pullRequest->agent_id)->toBeNull();
});
