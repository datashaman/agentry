<?php

use App\Models\Branch;
use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Repo;
use App\Models\Story;
use App\Models\Worktree;

test('can create a worktree', function () {
    $worktree = Worktree::factory()->create();

    expect($worktree)->toBeInstanceOf(Worktree::class)
        ->and($worktree->path)->not->toBeEmpty();
});

test('worktree belongs to repo', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::factory()->create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
    ]);

    expect($worktree->repo->id)->toBe($repo->id);
});

test('worktree belongs to branch', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::factory()->create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
    ]);

    expect($worktree->branch->id)->toBe($branch->id);
});

test('repo has many worktrees', function () {
    $repo = Repo::factory()->create();
    $branch1 = Branch::factory()->create(['repo_id' => $repo->id]);
    $branch2 = Branch::factory()->create(['repo_id' => $repo->id]);
    Worktree::factory()->create(['repo_id' => $repo->id, 'branch_id' => $branch1->id]);
    Worktree::factory()->create(['repo_id' => $repo->id, 'branch_id' => $branch2->id]);

    expect($repo->worktrees)->toHaveCount(2);
});

test('branch has many worktrees', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    Worktree::factory()->count(2)->create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
    ]);

    expect($branch->worktrees)->toHaveCount(2);
});

test('worktree polymorphically linked to story', function () {
    $story = Story::factory()->create();
    $worktree = Worktree::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($worktree->workItem)->toBeInstanceOf(Story::class)
        ->and($worktree->workItem->id)->toBe($story->id);
});

test('worktree polymorphically linked to bug', function () {
    $bug = Bug::factory()->create();
    $worktree = Worktree::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($worktree->workItem)->toBeInstanceOf(Bug::class)
        ->and($worktree->workItem->id)->toBe($bug->id);
});

test('worktree polymorphically linked to ops request', function () {
    $opsRequest = OpsRequest::factory()->create();
    $worktree = Worktree::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($worktree->workItem)->toBeInstanceOf(OpsRequest::class)
        ->and($worktree->workItem->id)->toBe($opsRequest->id);
});

test('story has many worktrees', function () {
    $story = Story::factory()->create();
    Worktree::factory()->count(2)->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($story->worktrees)->toHaveCount(2);
});

test('bug has many worktrees', function () {
    $bug = Bug::factory()->create();
    Worktree::factory()->count(2)->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($bug->worktrees)->toHaveCount(2);
});

test('ops request has many worktrees', function () {
    $opsRequest = OpsRequest::factory()->create();
    Worktree::factory()->count(2)->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($opsRequest->worktrees)->toHaveCount(2);
});

test('worktree status defaults to active', function () {
    $worktree = Worktree::factory()->create();

    expect($worktree->status)->toBe('active');
});

test('worktree supports all status values', function () {
    $statuses = ['active', 'interrupted', 'stale'];

    foreach ($statuses as $status) {
        $worktree = Worktree::factory()->create(['status' => $status]);
        expect($worktree->status)->toBe($status);
    }
});

test('worktree path is required', function () {
    expect(fn () => Worktree::factory()->create(['path' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('worktree casts last_activity_at as datetime', function () {
    $worktree = Worktree::factory()->create([
        'last_activity_at' => '2026-03-05 10:00:00',
    ]);

    expect($worktree->last_activity_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('worktree casts interrupted_at as datetime', function () {
    $worktree = Worktree::factory()->create([
        'status' => 'interrupted',
        'interrupted_at' => '2026-03-05 10:00:00',
        'interrupted_reason' => 'context switch',
    ]);

    expect($worktree->interrupted_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($worktree->interrupted_reason)->toBe('context switch');
});

test('worktree work item is nullable', function () {
    $worktree = Worktree::factory()->create([
        'work_item_id' => null,
        'work_item_type' => null,
    ]);

    expect($worktree->workItem)->toBeNull();
});

test('invariant worktree linked to exactly one branch and one work item', function () {
    $story = Story::factory()->create();
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::factory()->create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($worktree->branch)->toBeInstanceOf(Branch::class)
        ->and($worktree->workItem)->toBeInstanceOf(Story::class)
        ->and($worktree->branch->id)->toBe($branch->id)
        ->and($worktree->workItem->id)->toBe($story->id);
});

test('can update a worktree', function () {
    $worktree = Worktree::factory()->create();

    $worktree->update([
        'status' => 'interrupted',
        'interrupted_at' => now(),
        'interrupted_reason' => 'higher priority task',
    ]);

    $worktree->refresh();
    expect($worktree->status)->toBe('interrupted')
        ->and($worktree->interrupted_reason)->toBe('higher priority task');
});

test('can delete a worktree', function () {
    $worktree = Worktree::factory()->create();
    $worktreeId = $worktree->id;

    $worktree->delete();

    expect(Worktree::find($worktreeId))->toBeNull();
});

test('cascade delete when repo is deleted', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::factory()->create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
    ]);
    $worktreeId = $worktree->id;

    $repo->delete();

    expect(Worktree::find($worktreeId))->toBeNull();
});

test('cascade delete when branch is deleted', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::factory()->create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
    ]);
    $worktreeId = $worktree->id;

    $branch->delete();

    expect(Worktree::find($worktreeId))->toBeNull();
});

test('can list worktrees', function () {
    Worktree::factory()->count(5)->create();

    expect(Worktree::all())->toHaveCount(5);
});
