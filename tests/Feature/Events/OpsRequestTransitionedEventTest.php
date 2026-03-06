<?php

use App\Events\OpsRequestTransitioned;
use App\Models\OpsRequest;
use Illuminate\Support\Facades\Event;

test('OpsRequestTransitioned event fires on transitionTo', function () {
    Event::fake([OpsRequestTransitioned::class]);

    $opsRequest = OpsRequest::factory()->create(['status' => 'new']);

    $opsRequest->transitionTo('triaged');

    Event::assertDispatched(OpsRequestTransitioned::class, function (OpsRequestTransitioned $event) use ($opsRequest) {
        return $event->opsRequest->is($opsRequest)
            && $event->from === 'new'
            && $event->to === 'triaged';
    });
});

test('OpsRequestTransitioned event does not fire when transition is invalid', function () {
    Event::fake([OpsRequestTransitioned::class]);

    $opsRequest = OpsRequest::factory()->create(['status' => 'new']);

    try {
        $opsRequest->transitionTo('closed_done');
    } catch (\App\Exceptions\InvalidStatusTransitionException) {
        // expected
    }

    Event::assertNotDispatched(OpsRequestTransitioned::class);
});
