<?php

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\HitlEscalation;
use App\Models\OpsRequest;

// --- Valid transitions ---

test('ops request can transition from new to triaged', function () {
    $ops = OpsRequest::factory()->create(['status' => 'new']);

    $ops->transitionTo('triaged');

    expect($ops->fresh()->status)->toBe('triaged');
});

test('ops request can transition from new to closed_invalid', function () {
    $ops = OpsRequest::factory()->create(['status' => 'new']);

    $ops->transitionTo('closed_invalid');

    expect($ops->fresh()->status)->toBe('closed_invalid');
});

test('ops request can transition from triaged to planning', function () {
    $ops = OpsRequest::factory()->create(['status' => 'triaged']);

    $ops->transitionTo('planning');

    expect($ops->fresh()->status)->toBe('planning');
});

test('ops request can transition from planning to executing', function () {
    $ops = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'low']);

    $ops->transitionTo('executing');

    expect($ops->fresh()->status)->toBe('executing');
});

test('ops request can transition from executing to verifying', function () {
    $ops = OpsRequest::factory()->create(['status' => 'executing']);

    $ops->transitionTo('verifying');

    expect($ops->fresh()->status)->toBe('verifying');
});

test('ops request can transition from verifying to closed_done', function () {
    $ops = OpsRequest::factory()->create(['status' => 'verifying']);

    $ops->transitionTo('closed_done');

    expect($ops->fresh()->status)->toBe('closed_done');
});

test('ops request can transition from verifying to closed_rejected', function () {
    $ops = OpsRequest::factory()->create(['status' => 'verifying']);

    $ops->transitionTo('closed_rejected');

    expect($ops->fresh()->status)->toBe('closed_rejected');
});

// --- Full happy path ---

test('ops request can traverse full lifecycle', function () {
    $ops = OpsRequest::factory()->create(['status' => 'new', 'risk_level' => 'low']);

    $ops->transitionTo('triaged');
    $ops->transitionTo('planning');
    $ops->transitionTo('executing');
    $ops->transitionTo('verifying');
    $ops->transitionTo('closed_done');

    expect($ops->fresh()->status)->toBe('closed_done');
});

// --- Invalid transitions ---

test('ops request cannot transition from new to planning', function () {
    $ops = OpsRequest::factory()->create(['status' => 'new']);

    $ops->transitionTo('planning');
})->throws(InvalidStatusTransitionException::class);

test('ops request cannot transition from new to executing', function () {
    $ops = OpsRequest::factory()->create(['status' => 'new']);

    $ops->transitionTo('executing');
})->throws(InvalidStatusTransitionException::class);

test('ops request cannot transition from triaged to executing', function () {
    $ops = OpsRequest::factory()->create(['status' => 'triaged']);

    $ops->transitionTo('executing');
})->throws(InvalidStatusTransitionException::class);

test('ops request cannot transition from closed_done to any status', function () {
    $ops = OpsRequest::factory()->create(['status' => 'closed_done']);

    $ops->transitionTo('new');
})->throws(InvalidStatusTransitionException::class);

test('ops request cannot transition from closed_invalid to any status', function () {
    $ops = OpsRequest::factory()->create(['status' => 'closed_invalid']);

    $ops->transitionTo('new');
})->throws(InvalidStatusTransitionException::class);

test('ops request cannot transition from closed_rejected to any status', function () {
    $ops = OpsRequest::factory()->create(['status' => 'closed_rejected']);

    $ops->transitionTo('new');
})->throws(InvalidStatusTransitionException::class);

test('ops request cannot transition from planning to verifying', function () {
    $ops = OpsRequest::factory()->create(['status' => 'planning']);

    $ops->transitionTo('verifying');
})->throws(InvalidStatusTransitionException::class);

test('ops request cannot transition from executing to closed_done', function () {
    $ops = OpsRequest::factory()->create(['status' => 'executing']);

    $ops->transitionTo('closed_done');
})->throws(InvalidStatusTransitionException::class);

// --- Invariant: high/critical risk requires HITL approval before executing ---

test('high risk ops request cannot transition to executing with unresolved HITL escalation', function () {
    $ops = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'high']);

    HitlEscalation::factory()->create([
        'work_item_type' => OpsRequest::class,
        'work_item_id' => $ops->id,
        'resolved_at' => null,
    ]);

    $ops->transitionTo('executing');
})->throws(InvalidStatusTransitionException::class, 'High/critical risk ops requests require HITL approval before executing.');

test('critical risk ops request cannot transition to executing with unresolved HITL escalation', function () {
    $ops = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'critical']);

    HitlEscalation::factory()->create([
        'work_item_type' => OpsRequest::class,
        'work_item_id' => $ops->id,
        'resolved_at' => null,
    ]);

    $ops->transitionTo('executing');
})->throws(InvalidStatusTransitionException::class, 'High/critical risk ops requests require HITL approval before executing.');

test('high risk ops request can transition to executing when HITL escalation is resolved', function () {
    $ops = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'high']);

    HitlEscalation::factory()->resolved()->create([
        'work_item_type' => OpsRequest::class,
        'work_item_id' => $ops->id,
    ]);

    $ops->transitionTo('executing');

    expect($ops->fresh()->status)->toBe('executing');
});

test('critical risk ops request can transition to executing when HITL escalation is resolved', function () {
    $ops = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'critical']);

    HitlEscalation::factory()->resolved()->create([
        'work_item_type' => OpsRequest::class,
        'work_item_id' => $ops->id,
    ]);

    $ops->transitionTo('executing');

    expect($ops->fresh()->status)->toBe('executing');
});

test('low risk ops request can transition to executing without HITL approval', function () {
    $ops = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'low']);

    $ops->transitionTo('executing');

    expect($ops->fresh()->status)->toBe('executing');
});

test('medium risk ops request can transition to executing without HITL approval', function () {
    $ops = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'medium']);

    $ops->transitionTo('executing');

    expect($ops->fresh()->status)->toBe('executing');
});

// --- transitionTo returns self ---

test('transitionTo returns the ops request instance', function () {
    $ops = OpsRequest::factory()->create(['status' => 'new']);

    $result = $ops->transitionTo('triaged');

    expect($result)->toBe($ops);
});
