<?php

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Bug;
use App\Models\Critique;
use App\Models\Dependency;
use App\Models\HitlEscalation;
use App\Models\Story;

// --- Valid transitions ---

test('story can transition from backlog to refined', function () {
    $story = Story::factory()->create(['status' => 'backlog']);

    $story->transitionTo('refined');

    expect($story->fresh()->status)->toBe('refined');
});

test('story can transition from backlog to closed_wont_do', function () {
    $story = Story::factory()->create(['status' => 'backlog']);

    $story->transitionTo('closed_wont_do');

    expect($story->fresh()->status)->toBe('closed_wont_do');
});

test('story can transition from refined to sprint_planned', function () {
    $story = Story::factory()->create(['status' => 'refined']);

    $story->transitionTo('sprint_planned');

    expect($story->fresh()->status)->toBe('sprint_planned');
});

test('story can transition from refined to backlog', function () {
    $story = Story::factory()->create(['status' => 'refined']);

    $story->transitionTo('backlog');

    expect($story->fresh()->status)->toBe('backlog');
});

test('story can transition from sprint_planned to in_development', function () {
    $story = Story::factory()->create(['status' => 'sprint_planned']);

    $story->transitionTo('in_development');

    expect($story->fresh()->status)->toBe('in_development');
});

test('story can transition from sprint_planned to backlog', function () {
    $story = Story::factory()->create(['status' => 'sprint_planned']);

    $story->transitionTo('backlog');

    expect($story->fresh()->status)->toBe('backlog');
});

test('story can transition from in_development to in_review', function () {
    $story = Story::factory()->create(['status' => 'in_development']);

    $story->transitionTo('in_review');

    expect($story->fresh()->status)->toBe('in_review');
});

test('story can transition from in_development to blocked', function () {
    $story = Story::factory()->create(['status' => 'in_development']);

    $story->transitionTo('blocked');

    expect($story->fresh()->status)->toBe('blocked');
});

test('story can transition from in_review to staging', function () {
    $story = Story::factory()->create(['status' => 'in_review']);

    $story->transitionTo('staging');

    expect($story->fresh()->status)->toBe('staging');
});

test('story can transition from in_review to in_development', function () {
    $story = Story::factory()->create(['status' => 'in_review']);

    $story->transitionTo('in_development');

    expect($story->fresh()->status)->toBe('in_development');
});

test('story can transition from staging to merged', function () {
    $story = Story::factory()->create(['status' => 'staging']);

    $story->transitionTo('merged');

    expect($story->fresh()->status)->toBe('merged');
});

test('story can transition from merged to deployed', function () {
    $story = Story::factory()->create(['status' => 'merged']);

    $story->transitionTo('deployed');

    expect($story->fresh()->status)->toBe('deployed');
});

test('story can transition from deployed to closed_done', function () {
    $story = Story::factory()->create(['status' => 'deployed']);

    $story->transitionTo('closed_done');

    expect($story->fresh()->status)->toBe('closed_done');
});

test('story can transition from blocked to in_development when blocker resolved', function () {
    $blocker = Story::factory()->create(['status' => 'closed_done']);
    $story = Story::factory()->create(['status' => 'blocked']);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);

    $story->transitionTo('in_development');

    expect($story->fresh()->status)->toBe('in_development');
});

// --- Full happy path ---

test('story can traverse full lifecycle', function () {
    $story = Story::factory()->create(['status' => 'backlog']);

    $story->transitionTo('refined');
    $story->transitionTo('sprint_planned');
    $story->transitionTo('in_development');
    $story->transitionTo('in_review');
    $story->transitionTo('staging');
    $story->transitionTo('merged');
    $story->transitionTo('deployed');
    $story->transitionTo('closed_done');

    expect($story->fresh()->status)->toBe('closed_done');
});

// --- Invalid transitions ---

test('story cannot transition from backlog to in_development', function () {
    $story = Story::factory()->create(['status' => 'backlog']);

    $story->transitionTo('in_development');
})->throws(InvalidStatusTransitionException::class);

test('story cannot transition from closed_done to any status', function () {
    $story = Story::factory()->create(['status' => 'closed_done']);

    $story->transitionTo('backlog');
})->throws(InvalidStatusTransitionException::class);

test('story cannot transition from closed_wont_do to any status', function () {
    $story = Story::factory()->create(['status' => 'closed_wont_do']);

    $story->transitionTo('backlog');
})->throws(InvalidStatusTransitionException::class);

test('story cannot transition from staging to in_development', function () {
    $story = Story::factory()->create(['status' => 'staging']);

    $story->transitionTo('in_development');
})->throws(InvalidStatusTransitionException::class);

test('story cannot transition from merged to in_review', function () {
    $story = Story::factory()->create(['status' => 'merged']);

    $story->transitionTo('in_review');
})->throws(InvalidStatusTransitionException::class);

// --- Invariant: unresolved dependencies block in_development ---

test('story cannot transition to in_development with unresolved story dependency', function () {
    $blocker = Story::factory()->create(['status' => 'in_development']);
    $story = Story::factory()->create(['status' => 'sprint_planned']);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);

    $story->transitionTo('in_development');
})->throws(InvalidStatusTransitionException::class, 'Unresolved dependencies exist.');

test('story cannot transition to in_development with unresolved bug dependency', function () {
    $blocker = Bug::factory()->create(['status' => 'new']);
    $story = Story::factory()->create(['status' => 'sprint_planned']);

    Dependency::factory()->create([
        'blocker_type' => Bug::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);

    $story->transitionTo('in_development');
})->throws(InvalidStatusTransitionException::class, 'Unresolved dependencies exist.');

test('story can transition to in_development when dependency is resolved', function () {
    $blocker = Story::factory()->create(['status' => 'closed_done']);
    $story = Story::factory()->create(['status' => 'sprint_planned']);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);

    $story->transitionTo('in_development');

    expect($story->fresh()->status)->toBe('in_development');
});

// --- Invariant: unresolved HITL escalation blocks in_development ---

test('story cannot transition to in_development with unresolved HITL escalation', function () {
    $story = Story::factory()->create(['status' => 'sprint_planned']);

    HitlEscalation::factory()->create([
        'work_item_type' => Story::class,
        'work_item_id' => $story->id,
        'resolved_at' => null,
    ]);

    $story->transitionTo('in_development');
})->throws(InvalidStatusTransitionException::class, 'Unresolved HITL escalation exists.');

test('story can transition to in_development when HITL escalation is resolved', function () {
    $story = Story::factory()->create(['status' => 'sprint_planned']);

    HitlEscalation::factory()->resolved()->create([
        'work_item_type' => Story::class,
        'work_item_id' => $story->id,
    ]);

    $story->transitionTo('in_development');

    expect($story->fresh()->status)->toBe('in_development');
});

// --- Invariant: blocking critique prevents closed_done ---

test('story cannot transition to closed_done with blocking pending critique', function () {
    $story = Story::factory()->create(['status' => 'deployed']);

    Critique::factory()->create([
        'work_item_type' => Story::class,
        'work_item_id' => $story->id,
        'severity' => 'blocking',
        'disposition' => 'pending',
    ]);

    $story->transitionTo('closed_done');
})->throws(InvalidStatusTransitionException::class, 'Blocking critique with pending disposition exists.');

test('story can transition to closed_done when blocking critique is accepted', function () {
    $story = Story::factory()->create(['status' => 'deployed']);

    Critique::factory()->create([
        'work_item_type' => Story::class,
        'work_item_id' => $story->id,
        'severity' => 'blocking',
        'disposition' => 'accepted',
    ]);

    $story->transitionTo('closed_done');

    expect($story->fresh()->status)->toBe('closed_done');
});

test('story can transition to closed_done when critique is non-blocking', function () {
    $story = Story::factory()->create(['status' => 'deployed']);

    Critique::factory()->create([
        'work_item_type' => Story::class,
        'work_item_id' => $story->id,
        'severity' => 'minor',
        'disposition' => 'pending',
    ]);

    $story->transitionTo('closed_done');

    expect($story->fresh()->status)->toBe('closed_done');
});

// --- Blocked -> in_development when blocker resolved ---

test('story can transition from blocked to in_development when all blockers resolved', function () {
    $blocker1 = Story::factory()->create(['status' => 'closed_done']);
    $blocker2 = Bug::factory()->create(['status' => 'closed_fixed']);
    $story = Story::factory()->create(['status' => 'blocked']);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker1->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);

    Dependency::factory()->create([
        'blocker_type' => Bug::class,
        'blocker_id' => $blocker2->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);

    $story->transitionTo('in_development');

    expect($story->fresh()->status)->toBe('in_development');
});

test('story cannot transition from blocked to in_development when some blockers unresolved', function () {
    $blocker1 = Story::factory()->create(['status' => 'closed_done']);
    $blocker2 = Story::factory()->create(['status' => 'in_development']);
    $story = Story::factory()->create(['status' => 'blocked']);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker1->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker2->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);

    $story->transitionTo('in_development');
})->throws(InvalidStatusTransitionException::class, 'Unresolved dependencies exist.');

// --- transitionTo returns self ---

test('transitionTo returns the story instance', function () {
    $story = Story::factory()->create(['status' => 'backlog']);

    $result = $story->transitionTo('refined');

    expect($result)->toBe($story);
});
