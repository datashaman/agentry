<?php

use App\Models\Project;
use App\Models\WorkItem;
use App\Services\WorkItemClassifier;

test('classifies Jira work item using type_labels config', function () {
    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['type_labels' => ['Bug', 'Story', 'Task']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'jira',
        'type' => 'Bug',
    ]);

    expect((new WorkItemClassifier)->classify($workItem))->toBe('Bug');
});

test('classifies Jira work item case-insensitively', function () {
    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['type_labels' => ['Bug', 'Story']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'jira',
        'type' => 'bug',
    ]);

    expect((new WorkItemClassifier)->classify($workItem))->toBe('Bug');
});

test('classifies GitHub work item by matching label against type_labels', function () {
    $project = Project::factory()->create([
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['type_labels' => ['bug', 'enhancement', 'feature']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'github',
        'type' => 'enhancement',
    ]);

    expect((new WorkItemClassifier)->classify($workItem))->toBe('enhancement');
});

test('handles comma-separated labels for GitHub', function () {
    $project = Project::factory()->create([
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['type_labels' => ['bug', 'enhancement']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'github',
        'type' => 'wontfix, bug',
    ]);

    expect((new WorkItemClassifier)->classify($workItem))->toBe('bug');
});

test('returns null when no type_labels configured', function () {
    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['project_key' => 'PROJ'],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'jira',
        'type' => 'Bug',
    ]);

    expect((new WorkItemClassifier)->classify($workItem))->toBeNull();
});

test('returns null when type does not match any type_labels', function () {
    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['type_labels' => ['Bug', 'Story']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'jira',
        'type' => 'Epic',
    ]);

    expect((new WorkItemClassifier)->classify($workItem))->toBeNull();
});

test('returns null when work item has no project', function () {
    $workItem = WorkItem::factory()->make(['project_id' => null]);

    expect((new WorkItemClassifier)->classify($workItem))->toBeNull();
});
