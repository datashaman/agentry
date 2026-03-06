<?php

use App\Events\BugReported;
use App\Events\OpsRequestCreated;
use App\Events\StoryCreated;
use App\Events\WorkItemTracked;
use App\Listeners\DispatchWorkItemAgentWork;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Event;

test('classifies bug type and fires BugReported', function () {
    Event::fake([BugReported::class, StoryCreated::class, OpsRequestCreated::class]);

    $workItem = WorkItem::factory()->create(['type' => 'bug']);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    expect($workItem->fresh()->classified_type)->toBe('bug');

    Event::assertDispatched(BugReported::class, function (BugReported $event) use ($workItem) {
        return $event->workItem->is($workItem);
    });

    Event::assertNotDispatched(StoryCreated::class);
    Event::assertNotDispatched(OpsRequestCreated::class);
});

test('classifies story type and fires StoryCreated', function () {
    Event::fake([BugReported::class, StoryCreated::class, OpsRequestCreated::class]);

    $workItem = WorkItem::factory()->create(['type' => 'enhancement']);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    expect($workItem->fresh()->classified_type)->toBe('story');

    Event::assertDispatched(StoryCreated::class, function (StoryCreated $event) use ($workItem) {
        return $event->workItem->is($workItem);
    });

    Event::assertNotDispatched(BugReported::class);
    Event::assertNotDispatched(OpsRequestCreated::class);
});

test('classifies ops type and fires OpsRequestCreated', function () {
    Event::fake([BugReported::class, StoryCreated::class, OpsRequestCreated::class]);

    $workItem = WorkItem::factory()->create(['type' => 'deployment']);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    expect($workItem->fresh()->classified_type)->toBe('ops_request');

    Event::assertDispatched(OpsRequestCreated::class, function (OpsRequestCreated $event) use ($workItem) {
        return $event->workItem->is($workItem);
    });

    Event::assertNotDispatched(BugReported::class);
    Event::assertNotDispatched(StoryCreated::class);
});

test('classifies work item with no type as story', function () {
    Event::fake([BugReported::class, StoryCreated::class, OpsRequestCreated::class]);

    $workItem = WorkItem::factory()->create(['type' => null]);

    $event = new WorkItemTracked($workItem);
    app(DispatchWorkItemAgentWork::class)->handle($event);

    expect($workItem->fresh()->classified_type)->toBe('story');

    Event::assertDispatched(StoryCreated::class);
});
