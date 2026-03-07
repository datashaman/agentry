<?php

use App\Agents\ChatAgent;
use App\Models\Project;
use App\Models\WorkItem;
use App\Services\TypeSuggestionService;

test('suggests type labels for a project', function () {
    ChatAgent::fake(['["Bug", "Story", "Task", "Epic"]']);

    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['project_key' => 'PROJ'],
    ]);

    $service = app(TypeSuggestionService::class);
    $labels = $service->suggestTypeLabels($project);

    expect($labels)->toBe(['Bug', 'Story', 'Task', 'Epic']);
});

test('suggests classification for a work item', function () {
    ChatAgent::fake(['Bug']);

    $project = Project::factory()->create([
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['type_labels' => ['Bug', 'Story', 'Task']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'title' => 'Fix null pointer exception in login',
        'description' => 'Users get a 500 error when logging in with empty password',
        'type' => 'bug',
    ]);

    $service = app(TypeSuggestionService::class);
    $result = $service->suggestClassification($workItem, ['Bug', 'Story', 'Task']);

    expect($result)->toBe('Bug');
});

test('returns null when suggestion does not match any type label', function () {
    ChatAgent::fake(['Unknown']);

    $project = Project::factory()->create([
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['type_labels' => ['Bug', 'Story']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'title' => 'Some weird item',
        'type' => 'other',
    ]);

    $service = app(TypeSuggestionService::class);
    $result = $service->suggestClassification($workItem, ['Bug', 'Story']);

    expect($result)->toBeNull();
});

test('handles malformed JSON response for type labels', function () {
    ChatAgent::fake(['Not valid JSON']);

    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['project_key' => 'TEST'],
    ]);

    $service = app(TypeSuggestionService::class);
    $labels = $service->suggestTypeLabels($project);

    expect($labels)->toBe([]);
});
