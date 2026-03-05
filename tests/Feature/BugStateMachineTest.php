<?php

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Bug;
use App\Models\Dependency;
use App\Models\HitlEscalation;
use App\Models\Story;

// --- Valid transitions ---

test('bug can transition from new to triaged', function () {
    $bug = Bug::factory()->create(['status' => 'new']);

    $bug->transitionTo('triaged');

    expect($bug->fresh()->status)->toBe('triaged');
});

test('bug can transition from new to closed_duplicate', function () {
    $bug = Bug::factory()->create(['status' => 'new']);

    $bug->transitionTo('closed_duplicate');

    expect($bug->fresh()->status)->toBe('closed_duplicate');
});

test('bug can transition from new to closed_cant_reproduce', function () {
    $bug = Bug::factory()->create(['status' => 'new']);

    $bug->transitionTo('closed_cant_reproduce');

    expect($bug->fresh()->status)->toBe('closed_cant_reproduce');
});

test('bug can transition from triaged to in_progress', function () {
    $bug = Bug::factory()->create(['status' => 'triaged']);

    $bug->transitionTo('in_progress');

    expect($bug->fresh()->status)->toBe('in_progress');
});

test('bug can transition from in_progress to in_review', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);

    $bug->transitionTo('in_review');

    expect($bug->fresh()->status)->toBe('in_review');
});

test('bug can transition from in_progress to blocked', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);

    $bug->transitionTo('blocked');

    expect($bug->fresh()->status)->toBe('blocked');
});

test('bug can transition from in_review to verified', function () {
    $bug = Bug::factory()->create(['status' => 'in_review']);

    $bug->transitionTo('verified');

    expect($bug->fresh()->status)->toBe('verified');
});

test('bug can transition from in_review to in_progress', function () {
    $bug = Bug::factory()->create(['status' => 'in_review']);

    $bug->transitionTo('in_progress');

    expect($bug->fresh()->status)->toBe('in_progress');
});

test('bug can transition from verified to released', function () {
    $bug = Bug::factory()->create(['status' => 'verified']);

    $bug->transitionTo('released');

    expect($bug->fresh()->status)->toBe('released');
});

test('bug can transition from released to closed_fixed', function () {
    $bug = Bug::factory()->create(['status' => 'released']);

    $bug->transitionTo('closed_fixed');

    expect($bug->fresh()->status)->toBe('closed_fixed');
});

test('bug can transition from blocked to in_progress when blocker resolved', function () {
    $blocker = Story::factory()->create(['status' => 'closed_done']);
    $bug = Bug::factory()->create(['status' => 'blocked']);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Bug::class,
        'blocked_id' => $bug->id,
    ]);

    $bug->transitionTo('in_progress');

    expect($bug->fresh()->status)->toBe('in_progress');
});

// --- Full happy path ---

test('bug can traverse full lifecycle', function () {
    $bug = Bug::factory()->create(['status' => 'new']);

    $bug->transitionTo('triaged');
    $bug->transitionTo('in_progress');
    $bug->transitionTo('in_review');
    $bug->transitionTo('verified');
    $bug->transitionTo('released');
    $bug->transitionTo('closed_fixed');

    expect($bug->fresh()->status)->toBe('closed_fixed');
});

// --- Invalid transitions ---

test('bug cannot transition from new to in_progress', function () {
    $bug = Bug::factory()->create(['status' => 'new']);

    $bug->transitionTo('in_progress');
})->throws(InvalidStatusTransitionException::class);

test('bug cannot transition from closed_fixed to any status', function () {
    $bug = Bug::factory()->create(['status' => 'closed_fixed']);

    $bug->transitionTo('new');
})->throws(InvalidStatusTransitionException::class);

test('bug cannot transition from closed_duplicate to any status', function () {
    $bug = Bug::factory()->create(['status' => 'closed_duplicate']);

    $bug->transitionTo('new');
})->throws(InvalidStatusTransitionException::class);

test('bug cannot transition from closed_cant_reproduce to any status', function () {
    $bug = Bug::factory()->create(['status' => 'closed_cant_reproduce']);

    $bug->transitionTo('new');
})->throws(InvalidStatusTransitionException::class);

test('bug cannot transition from triaged to verified', function () {
    $bug = Bug::factory()->create(['status' => 'triaged']);

    $bug->transitionTo('verified');
})->throws(InvalidStatusTransitionException::class);

test('bug cannot transition from verified to in_progress', function () {
    $bug = Bug::factory()->create(['status' => 'verified']);

    $bug->transitionTo('in_progress');
})->throws(InvalidStatusTransitionException::class);

// --- Invariant: unresolved HITL escalation blocks in_progress ---

test('bug cannot transition to in_progress with unresolved HITL escalation', function () {
    $bug = Bug::factory()->create(['status' => 'triaged']);

    HitlEscalation::factory()->create([
        'work_item_type' => Bug::class,
        'work_item_id' => $bug->id,
        'resolved_at' => null,
    ]);

    $bug->transitionTo('in_progress');
})->throws(InvalidStatusTransitionException::class, 'Unresolved HITL escalation exists.');

test('bug can transition to in_progress when HITL escalation is resolved', function () {
    $bug = Bug::factory()->create(['status' => 'triaged']);

    HitlEscalation::factory()->resolved()->create([
        'work_item_type' => Bug::class,
        'work_item_id' => $bug->id,
    ]);

    $bug->transitionTo('in_progress');

    expect($bug->fresh()->status)->toBe('in_progress');
});

// --- Blocked -> in_progress when blocker resolved ---

test('bug can transition from blocked to in_progress when all blockers resolved', function () {
    $blocker1 = Story::factory()->create(['status' => 'closed_done']);
    $blocker2 = Bug::factory()->create(['status' => 'closed_fixed']);
    $bug = Bug::factory()->create(['status' => 'blocked']);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker1->id,
        'blocked_type' => Bug::class,
        'blocked_id' => $bug->id,
    ]);

    Dependency::factory()->create([
        'blocker_type' => Bug::class,
        'blocker_id' => $blocker2->id,
        'blocked_type' => Bug::class,
        'blocked_id' => $bug->id,
    ]);

    $bug->transitionTo('in_progress');

    expect($bug->fresh()->status)->toBe('in_progress');
});

test('bug cannot transition from blocked to in_progress when some blockers unresolved', function () {
    $blocker1 = Story::factory()->create(['status' => 'closed_done']);
    $blocker2 = Story::factory()->create(['status' => 'in_development']);
    $bug = Bug::factory()->create(['status' => 'blocked']);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker1->id,
        'blocked_type' => Bug::class,
        'blocked_id' => $bug->id,
    ]);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker2->id,
        'blocked_type' => Bug::class,
        'blocked_id' => $bug->id,
    ]);

    $bug->transitionTo('in_progress');
})->throws(InvalidStatusTransitionException::class, 'Unresolved dependencies exist.');

// --- transitionTo returns self ---

test('transitionTo returns the bug instance', function () {
    $bug = Bug::factory()->create(['status' => 'new']);

    $result = $bug->transitionTo('triaged');

    expect($result)->toBe($bug);
});
