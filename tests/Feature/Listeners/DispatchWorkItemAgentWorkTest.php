<?php

use App\Events\WorkItemClassified;
use App\Events\WorkItemTracked;
use App\Listeners\DispatchWorkItemAgentWork;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Event;

test('classifies bug type and fires WorkItemClassified', function () {
    Event::fake([WorkItemClassified::class]);

    $workItem = WorkItem::factory()->create(['type' => 'bug']);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    expect($workItem->fresh()->classified_type)->toBe('bug');

    Event::assertDispatched(WorkItemClassified::class, function (WorkItemClassified $event) use ($workItem) {
        return $event->workItem->is($workItem);
    });
});

test('classifies story type and fires WorkItemClassified', function () {
    Event::fake([WorkItemClassified::class]);

    $workItem = WorkItem::factory()->create(['type' => 'enhancement']);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    expect($workItem->fresh()->classified_type)->toBe('story');

    Event::assertDispatched(WorkItemClassified::class, function (WorkItemClassified $event) use ($workItem) {
        return $event->workItem->is($workItem);
    });
});

test('classifies ops type and fires WorkItemClassified', function () {
    Event::fake([WorkItemClassified::class]);

    $workItem = WorkItem::factory()->create(['type' => 'deployment']);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    expect($workItem->fresh()->classified_type)->toBe('ops_request');

    Event::assertDispatched(WorkItemClassified::class, function (WorkItemClassified $event) use ($workItem) {
        return $event->workItem->is($workItem);
    });
});

test('classifies work item with no type as story', function () {
    Event::fake([WorkItemClassified::class]);

    $workItem = WorkItem::factory()->create(['type' => null]);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    expect($workItem->fresh()->classified_type)->toBe('story');

    Event::assertDispatched(WorkItemClassified::class);
});
