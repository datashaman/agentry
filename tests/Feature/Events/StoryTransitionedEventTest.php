<?php

use App\Events\StoryTransitioned;
use App\Models\Story;
use Illuminate\Support\Facades\Event;

test('StoryTransitioned event fires on transitionTo', function () {
    Event::fake([StoryTransitioned::class]);

    $story = Story::factory()->create(['status' => 'backlog']);

    $story->transitionTo('refined');

    Event::assertDispatched(StoryTransitioned::class, function (StoryTransitioned $event) use ($story) {
        return $event->story->is($story)
            && $event->from === 'backlog'
            && $event->to === 'refined';
    });
});

test('StoryTransitioned event carries correct from and to for chained transitions', function () {
    Event::fake([StoryTransitioned::class]);

    $story = Story::factory()->create(['status' => 'backlog']);

    $story->transitionTo('spec_critique');

    Event::assertDispatched(StoryTransitioned::class, function (StoryTransitioned $event) {
        return $event->from === 'backlog' && $event->to === 'spec_critique';
    });
});

test('StoryTransitioned event does not fire when transition is invalid', function () {
    Event::fake([StoryTransitioned::class]);

    $story = Story::factory()->create(['status' => 'backlog']);

    try {
        $story->transitionTo('merged');
    } catch (\App\Exceptions\InvalidStatusTransitionException) {
        // expected
    }

    Event::assertNotDispatched(StoryTransitioned::class);
});
