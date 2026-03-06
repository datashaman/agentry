<?php

use App\Models\WorkItem;
use App\Services\WorkItemClassifier;

test('classifies bug labels as bug', function (string $type) {
    $workItem = WorkItem::factory()->make(['type' => $type]);

    expect((new WorkItemClassifier)->classify($workItem))->toBe('bug');
})->with(['bug', 'Bug', 'defect', 'error', 'bug,enhancement']);

test('classifies ops labels as ops_request', function (string $type) {
    $workItem = WorkItem::factory()->make(['type' => $type]);

    expect((new WorkItemClassifier)->classify($workItem))->toBe('ops_request');
})->with(['ops', 'operations', 'deployment', 'infrastructure', 'incident', 'Deployment']);

test('ops_request takes priority over bug', function () {
    $workItem = WorkItem::factory()->make(['type' => 'bug,infrastructure']);

    expect((new WorkItemClassifier)->classify($workItem))->toBe('ops_request');
});

test('classifies everything else as story', function (string $type) {
    $workItem = WorkItem::factory()->make(['type' => $type]);

    expect((new WorkItemClassifier)->classify($workItem))->toBe('story');
})->with(['enhancement', 'feature', 'Story', 'Task', 'epic', 'Issue']);

test('classifies null type as story', function () {
    $workItem = WorkItem::factory()->make(['type' => null]);

    expect((new WorkItemClassifier)->classify($workItem))->toBe('story');
});

test('classifies empty type as story', function () {
    $workItem = WorkItem::factory()->make(['type' => '']);

    expect((new WorkItemClassifier)->classify($workItem))->toBe('story');
});
