<?php

use App\Agents\ChatAgent;
use App\Events\WorkItemClassified;
use App\Events\WorkItemTracked;
use App\Listeners\DispatchWorkItemAgentWork;
use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Event;

test('creates HITL escalation for type label suggestions when none configured', function () {
    Event::fake([WorkItemClassified::class]);
    ChatAgent::fake(['["Bug", "Story", "Task"]']);

    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['project_key' => 'PROJ'],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'type' => 'Bug',
    ]);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    Event::assertNotDispatched(WorkItemClassified::class);

    $escalation = $workItem->hitlEscalations()->first();
    expect($escalation)->not->toBeNull()
        ->and($escalation->trigger_type)->toBe('type_label_suggestion')
        ->and($escalation->metadata['suggested_labels'])->toBe(['Bug', 'Story', 'Task']);
});

test('creates HITL escalation on reclassification when type differs', function () {
    Event::fake([WorkItemClassified::class]);

    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['type_labels' => ['Bug', 'Story', 'Task']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'jira',
        'type' => 'Epic',
    ]);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    Event::assertNotDispatched(WorkItemClassified::class);

    $escalation = $workItem->hitlEscalations()->first();
    expect($escalation)->not->toBeNull()
        ->and($escalation->trigger_type)->toBe('reclassification')
        ->and($escalation->metadata['original_type'])->toBe('Epic');
});

test('dispatches WorkItemClassified when classification matches provider type', function () {
    Event::fake([WorkItemClassified::class]);

    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['type_labels' => ['Bug', 'Story', 'Task']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'jira',
        'type' => 'Bug',
    ]);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    expect($workItem->fresh()->classified_type)->toBe('Bug');

    Event::assertDispatched(WorkItemClassified::class, function (WorkItemClassified $e) use ($workItem) {
        return $e->workItem->is($workItem);
    });

    expect($workItem->hitlEscalations()->count())->toBe(0);
});

test('does not dispatch WorkItemClassified when escalation is pending', function () {
    Event::fake([WorkItemClassified::class]);

    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['type_labels' => ['Bug', 'Story']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'jira',
        'type' => 'Task',
    ]);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    Event::assertNotDispatched(WorkItemClassified::class);
    expect($workItem->hasPendingEscalation())->toBeTrue();
});

test('does not create duplicate escalation when pending escalation exists', function () {
    Event::fake([WorkItemClassified::class]);
    ChatAgent::fake(['["Bug", "Story", "Task"]', '["Bug", "Story", "Task"]']);

    $project = Project::factory()->create([
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['project_key' => 'PROJ'],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'type' => 'Bug',
    ]);

    $listener = app(DispatchWorkItemAgentWork::class);
    $event = new WorkItemTracked($workItem);

    $listener->handle($event);
    expect($workItem->hitlEscalations()->count())->toBe(1);

    $listener->handle($event);
    expect($workItem->hitlEscalations()->count())->toBe(1);
});

test('stores classified_type even when creating reclassification escalation', function () {
    Event::fake([WorkItemClassified::class]);

    $project = Project::factory()->create([
        'work_item_provider' => 'github',
        'work_item_provider_config' => ['type_labels' => ['bug', 'enhancement']],
    ]);

    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'provider' => 'github',
        'type' => 'bug, wontfix',
    ]);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    expect($workItem->fresh()->classified_type)->toBe('bug');
});
