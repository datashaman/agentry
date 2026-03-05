<?php

use App\Models\Branch;
use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Repo;
use App\Models\Story;

test('can create a branch', function () {
    $branch = Branch::factory()->create();

    expect($branch)->toBeInstanceOf(Branch::class)
        ->and($branch->name)->not->toBeEmpty();
});

test('branch belongs to repo', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);

    expect($branch->repo->id)->toBe($repo->id);
});

test('repo has many branches', function () {
    $repo = Repo::factory()->create();
    Branch::factory()->count(3)->create(['repo_id' => $repo->id]);

    expect($repo->branches)->toHaveCount(3);
});

test('branch name is required', function () {
    expect(fn () => Branch::factory()->create(['name' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('branch base_branch defaults to main', function () {
    $branch = Branch::factory()->create();

    expect($branch->base_branch)->toBe('main');
});

test('branch polymorphically linked to story', function () {
    $story = Story::factory()->create();
    $branch = Branch::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($branch->workItem)->toBeInstanceOf(Story::class)
        ->and($branch->workItem->id)->toBe($story->id);
});

test('branch polymorphically linked to bug', function () {
    $bug = Bug::factory()->create();
    $branch = Branch::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($branch->workItem)->toBeInstanceOf(Bug::class)
        ->and($branch->workItem->id)->toBe($bug->id);
});

test('branch polymorphically linked to ops request', function () {
    $opsRequest = OpsRequest::factory()->create();
    $branch = Branch::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($branch->workItem)->toBeInstanceOf(OpsRequest::class)
        ->and($branch->workItem->id)->toBe($opsRequest->id);
});

test('story has many branches', function () {
    $story = Story::factory()->create();
    Branch::factory()->count(2)->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($story->branches)->toHaveCount(2);
});

test('bug has many branches', function () {
    $bug = Bug::factory()->create();
    Branch::factory()->count(2)->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($bug->branches)->toHaveCount(2);
});

test('ops request has many branches', function () {
    $opsRequest = OpsRequest::factory()->create();
    Branch::factory()->count(2)->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($opsRequest->branches)->toHaveCount(2);
});

test('branch work item is nullable', function () {
    $branch = Branch::factory()->create([
        'work_item_id' => null,
        'work_item_type' => null,
    ]);

    expect($branch->workItem)->toBeNull();
});

test('can update a branch', function () {
    $branch = Branch::factory()->create();

    $branch->update([
        'name' => 'feature/updated-branch',
        'base_branch' => 'develop',
    ]);

    $branch->refresh();
    expect($branch->name)->toBe('feature/updated-branch')
        ->and($branch->base_branch)->toBe('develop');
});

test('can delete a branch', function () {
    $branch = Branch::factory()->create();
    $branchId = $branch->id;

    $branch->delete();

    expect(Branch::find($branchId))->toBeNull();
});

test('cascade delete when repo is deleted', function () {
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $branchId = $branch->id;

    $repo->delete();

    expect(Branch::find($branchId))->toBeNull();
});

test('can list branches', function () {
    Branch::factory()->count(5)->create();

    expect(Branch::all())->toHaveCount(5);
});

test('branch factory forStory state works', function () {
    $story = Story::factory()->create();
    $branch = Branch::factory()->forStory($story)->create();

    expect($branch->work_item_type)->toBe(Story::class)
        ->and($branch->work_item_id)->toBe($story->id);
});
