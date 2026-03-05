<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use App\Models\Story;

test('can create a hitl escalation', function () {
    $escalation = HitlEscalation::factory()->create();

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->trigger_type)->not->toBeEmpty()
        ->and($escalation->reason)->not->toBeEmpty();
});

test('escalation polymorphically belongs to story', function () {
    $story = Story::factory()->create();
    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($escalation->workItem)->toBeInstanceOf(Story::class)
        ->and($escalation->workItem->id)->toBe($story->id);
});

test('escalation polymorphically belongs to bug', function () {
    $bug = Bug::factory()->create();
    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($escalation->workItem)->toBeInstanceOf(Bug::class)
        ->and($escalation->workItem->id)->toBe($bug->id);
});

test('escalation polymorphically belongs to ops request', function () {
    $opsRequest = OpsRequest::factory()->create();
    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($escalation->workItem)->toBeInstanceOf(OpsRequest::class)
        ->and($escalation->workItem->id)->toBe($opsRequest->id);
});

test('escalation belongs to agent (raised_by)', function () {
    $agent = Agent::factory()->create();
    $escalation = HitlEscalation::factory()->create(['raised_by_agent_id' => $agent->id]);

    expect($escalation->raisedByAgent)->toBeInstanceOf(Agent::class)
        ->and($escalation->raisedByAgent->id)->toBe($agent->id);
});

test('story has many hitl escalations', function () {
    $story = Story::factory()->create();
    HitlEscalation::factory()->count(3)->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($story->hitlEscalations)->toHaveCount(3);
});

test('bug has many hitl escalations', function () {
    $bug = Bug::factory()->create();
    HitlEscalation::factory()->count(2)->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($bug->hitlEscalations)->toHaveCount(2);
});

test('ops request has many hitl escalations', function () {
    $opsRequest = OpsRequest::factory()->create();
    HitlEscalation::factory()->count(2)->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($opsRequest->hitlEscalations)->toHaveCount(2);
});

test('agent has many hitl escalations', function () {
    $agent = Agent::factory()->create();
    HitlEscalation::factory()->count(2)->create(['raised_by_agent_id' => $agent->id]);

    expect($agent->hitlEscalations)->toHaveCount(2);
});

test('trigger type supports all values', function () {
    foreach (['confidence', 'risk', 'policy', 'ambiguity'] as $type) {
        $escalation = HitlEscalation::factory()->create(['trigger_type' => $type]);
        expect($escalation->trigger_type)->toBe($type);
    }
});

test('agent confidence is cast to float', function () {
    $escalation = HitlEscalation::factory()->create(['agent_confidence' => 0.85]);

    expect($escalation->agent_confidence)->toBeFloat()
        ->and($escalation->agent_confidence)->toBe(0.85);
});

test('resolved_at is cast to datetime', function () {
    $escalation = HitlEscalation::factory()->resolved()->create();

    expect($escalation->resolved_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('nullable fields accept null', function () {
    $story = Story::factory()->create();
    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'raised_by_agent_id' => null,
        'trigger_class' => null,
        'agent_confidence' => null,
        'resolution' => null,
        'resolved_by' => null,
        'resolved_at' => null,
    ]);

    expect($escalation->raised_by_agent_id)->toBeNull()
        ->and($escalation->trigger_class)->toBeNull()
        ->and($escalation->agent_confidence)->toBeNull()
        ->and($escalation->resolution)->toBeNull()
        ->and($escalation->resolved_by)->toBeNull()
        ->and($escalation->resolved_at)->toBeNull();
});

test('isResolved returns true when resolved_at is set', function () {
    $escalation = HitlEscalation::factory()->resolved()->create();

    expect($escalation->isResolved())->toBeTrue();
});

test('isResolved returns false when resolved_at is null', function () {
    $escalation = HitlEscalation::factory()->create();

    expect($escalation->isResolved())->toBeFalse();
});

test('unresolved escalation blocks story progress', function () {
    $story = Story::factory()->create();
    HitlEscalation::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($story->hasUnresolvedEscalation())->toBeTrue();
});

test('resolved escalation does not block story progress', function () {
    $story = Story::factory()->create();
    HitlEscalation::factory()->resolved()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($story->hasUnresolvedEscalation())->toBeFalse();
});

test('unresolved escalation blocks bug progress', function () {
    $bug = Bug::factory()->create();
    HitlEscalation::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($bug->hasUnresolvedEscalation())->toBeTrue();
});

test('unresolved escalation blocks ops request progress', function () {
    $opsRequest = OpsRequest::factory()->create();
    HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($opsRequest->hasUnresolvedEscalation())->toBeTrue();
});

test('mixed resolved and unresolved escalations still block', function () {
    $story = Story::factory()->create();
    HitlEscalation::factory()->resolved()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);
    HitlEscalation::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($story->hasUnresolvedEscalation())->toBeTrue();
});

test('no escalations means not blocked', function () {
    $story = Story::factory()->create();

    expect($story->hasUnresolvedEscalation())->toBeFalse();
});

test('resolution flow works', function () {
    $escalation = HitlEscalation::factory()->create();

    expect($escalation->isResolved())->toBeFalse();

    $escalation->update([
        'resolution' => 'Approved by human reviewer',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);

    expect($escalation->fresh()->isResolved())->toBeTrue();
});

test('can update escalation', function () {
    $escalation = HitlEscalation::factory()->create();
    $escalation->update(['reason' => 'Updated reason']);

    expect($escalation->fresh()->reason)->toBe('Updated reason');
});

test('can delete escalation', function () {
    $escalation = HitlEscalation::factory()->create();
    $id = $escalation->id;
    $escalation->delete();

    expect(HitlEscalation::find($id))->toBeNull();
});

test('null on delete for agent', function () {
    $agent = Agent::factory()->create();
    $escalation = HitlEscalation::factory()->create(['raised_by_agent_id' => $agent->id]);

    $agent->delete();

    expect($escalation->fresh()->raised_by_agent_id)->toBeNull();
});

test('nullable work item', function () {
    $escalation = HitlEscalation::factory()->create([
        'work_item_id' => null,
        'work_item_type' => null,
    ]);

    expect($escalation->work_item_id)->toBeNull()
        ->and($escalation->work_item_type)->toBeNull()
        ->and($escalation->workItem)->toBeNull();
});
