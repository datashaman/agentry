<?php

use App\Models\Bug;
use App\Models\ChangeSet;
use App\Models\OpsRequest;
use App\Models\Story;

test('can create a change set', function () {
    $changeSet = ChangeSet::factory()->create();

    expect($changeSet)->toBeInstanceOf(ChangeSet::class)
        ->and($changeSet->summary)->not->toBeEmpty();
});

test('change set status defaults to draft', function () {
    $changeSet = ChangeSet::create(['summary' => 'test']);
    $changeSet->refresh();

    expect($changeSet->status)->toBe('draft');
});

test('change set supports all status values', function ($status) {
    $changeSet = ChangeSet::factory()->create(['status' => $status]);

    expect($changeSet->status)->toBe($status);
})->with(['draft', 'ready', 'merged', 'reverted']);

test('change set polymorphically linked to story', function () {
    $story = Story::factory()->create();
    $changeSet = ChangeSet::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($changeSet->workItem)->toBeInstanceOf(Story::class)
        ->and($changeSet->workItem->id)->toBe($story->id);
});

test('change set polymorphically linked to bug', function () {
    $bug = Bug::factory()->create();
    $changeSet = ChangeSet::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($changeSet->workItem)->toBeInstanceOf(Bug::class)
        ->and($changeSet->workItem->id)->toBe($bug->id);
});

test('change set polymorphically linked to ops request', function () {
    $opsRequest = OpsRequest::factory()->create();
    $changeSet = ChangeSet::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($changeSet->workItem)->toBeInstanceOf(OpsRequest::class)
        ->and($changeSet->workItem->id)->toBe($opsRequest->id);
});

test('story has many change sets', function () {
    $story = Story::factory()->create();
    ChangeSet::factory()->count(3)->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($story->changeSets)->toHaveCount(3);
});

test('bug has many change sets', function () {
    $bug = Bug::factory()->create();
    ChangeSet::factory()->count(2)->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($bug->changeSets)->toHaveCount(2);
});

test('ops request has many change sets', function () {
    $opsRequest = OpsRequest::factory()->create();
    ChangeSet::factory()->count(2)->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($opsRequest->changeSets)->toHaveCount(2);
});

test('change set work item is nullable', function () {
    $changeSet = ChangeSet::factory()->create([
        'work_item_id' => null,
        'work_item_type' => null,
    ]);

    expect($changeSet->workItem)->toBeNull();
});

test('change set summary is nullable', function () {
    $changeSet = ChangeSet::factory()->create(['summary' => null]);

    expect($changeSet->summary)->toBeNull();
});

test('can update a change set', function () {
    $changeSet = ChangeSet::factory()->create(['status' => 'draft']);

    $changeSet->update(['status' => 'ready', 'summary' => 'Updated summary']);

    $changeSet->refresh();
    expect($changeSet->status)->toBe('ready')
        ->and($changeSet->summary)->toBe('Updated summary');
});

test('can delete a change set', function () {
    $changeSet = ChangeSet::factory()->create();
    $id = $changeSet->id;

    $changeSet->delete();

    expect(ChangeSet::find($id))->toBeNull();
});

test('can list change sets', function () {
    ChangeSet::factory()->count(5)->create();

    expect(ChangeSet::all())->toHaveCount(5);
});

test('change set factory forStory state works', function () {
    $story = Story::factory()->create();
    $changeSet = ChangeSet::factory()->forStory($story)->create();

    expect($changeSet->work_item_type)->toBe(Story::class)
        ->and($changeSet->work_item_id)->toBe($story->id);
});
