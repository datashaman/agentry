<?php

use App\Events\BugTransitioned;
use App\Models\Bug;
use Illuminate\Support\Facades\Event;

test('BugTransitioned event fires on transitionTo', function () {
    Event::fake([BugTransitioned::class]);

    $bug = Bug::factory()->create(['status' => 'new']);

    $bug->transitionTo('triaged');

    Event::assertDispatched(BugTransitioned::class, function (BugTransitioned $event) use ($bug) {
        return $event->bug->is($bug)
            && $event->from === 'new'
            && $event->to === 'triaged';
    });
});

test('BugTransitioned event does not fire when transition is invalid', function () {
    Event::fake([BugTransitioned::class]);

    $bug = Bug::factory()->create(['status' => 'new']);

    try {
        $bug->transitionTo('closed_fixed');
    } catch (\App\Exceptions\InvalidStatusTransitionException) {
        // expected
    }

    Event::assertNotDispatched(BugTransitioned::class);
});
