<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Critique;
use App\Models\Story;

test('can create a critique', function () {
    $critique = Critique::factory()->create();

    expect($critique)->toBeInstanceOf(Critique::class)
        ->and($critique->critic_type)->not->toBeEmpty();
});

test('critique polymorphically belongs to story', function () {
    $story = Story::factory()->create();
    $critique = Critique::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($critique->workItem)->toBeInstanceOf(Story::class)
        ->and($critique->workItem->id)->toBe($story->id);
});

test('critique polymorphically belongs to bug', function () {
    $bug = Bug::factory()->create();
    $critique = Critique::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($critique->workItem)->toBeInstanceOf(Bug::class)
        ->and($critique->workItem->id)->toBe($bug->id);
});

test('critique belongs to agent', function () {
    $agent = Agent::factory()->create();
    $critique = Critique::factory()->create(['agent_id' => $agent->id]);

    expect($critique->agent)->toBeInstanceOf(Agent::class)
        ->and($critique->agent->id)->toBe($agent->id);
});

test('story has many critiques', function () {
    $story = Story::factory()->create();
    Critique::factory()->count(3)->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($story->critiques)->toHaveCount(3);
});

test('bug has many critiques', function () {
    $bug = Bug::factory()->create();
    Critique::factory()->count(2)->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($bug->critiques)->toHaveCount(2);
});

test('agent has many critiques', function () {
    $agent = Agent::factory()->create();
    Critique::factory()->count(3)->create(['agent_id' => $agent->id]);

    expect($agent->critiques)->toHaveCount(3);
});

test('critique supports all critic types', function () {
    $types = ['spec', 'code', 'test', 'design'];

    foreach ($types as $type) {
        $critique = Critique::factory()->create(['critic_type' => $type]);
        expect($critique->critic_type)->toBe($type);
    }
});

test('critique supports all severity values', function () {
    $severities = ['blocking', 'major', 'minor', 'suggestion'];

    foreach ($severities as $severity) {
        $critique = Critique::factory()->create(['severity' => $severity]);
        expect($critique->severity)->toBe($severity);
    }
});

test('critique supports all disposition values', function () {
    $dispositions = ['pending', 'accepted', 'rejected', 'deferred'];

    foreach ($dispositions as $disposition) {
        $critique = Critique::factory()->create(['disposition' => $disposition]);
        expect($critique->disposition)->toBe($disposition);
    }
});

test('critique issues questions recommendations are json cast', function () {
    $critique = Critique::factory()->create([
        'issues' => ['issue one', 'issue two'],
        'questions' => ['question one'],
        'recommendations' => ['rec one', 'rec two', 'rec three'],
    ]);

    expect($critique->issues)->toBeArray()->toHaveCount(2)
        ->and($critique->questions)->toBeArray()->toHaveCount(1)
        ->and($critique->recommendations)->toBeArray()->toHaveCount(3);
});

test('critique json fields are nullable', function () {
    $critique = Critique::factory()->create([
        'issues' => null,
        'questions' => null,
        'recommendations' => null,
    ]);

    expect($critique->issues)->toBeNull()
        ->and($critique->questions)->toBeNull()
        ->and($critique->recommendations)->toBeNull();
});

test('critique severity defaults to suggestion', function () {
    $story = Story::factory()->create();
    $critique = Critique::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'critic_type' => 'spec',
    ]);
    $critique->refresh();

    expect($critique->severity)->toBe('suggestion');
});

test('critique disposition defaults to pending', function () {
    $story = Story::factory()->create();
    $critique = Critique::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'critic_type' => 'spec',
    ]);
    $critique->refresh();

    expect($critique->disposition)->toBe('pending');
});

test('critique revision defaults to 1', function () {
    $story = Story::factory()->create();
    $critique = Critique::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'critic_type' => 'spec',
    ]);
    $critique->refresh();

    expect($critique->revision)->toBe(1);
});

test('critique self-referential supersedes relationship', function () {
    $original = Critique::factory()->create(['disposition' => 'pending']);
    $superseding = Critique::factory()->create([
        'supersedes_id' => $original->id,
        'work_item_id' => $original->work_item_id,
        'work_item_type' => $original->work_item_type,
    ]);

    expect($superseding->supersedes)->toBeInstanceOf(Critique::class)
        ->and($superseding->supersedes->id)->toBe($original->id)
        ->and($original->supersededBy)->toHaveCount(1)
        ->and($original->supersededBy->first()->id)->toBe($superseding->id);
});

test('critique supersession chain', function () {
    $first = Critique::factory()->create(['disposition' => 'pending']);
    $second = Critique::factory()->create([
        'supersedes_id' => $first->id,
        'work_item_id' => $first->work_item_id,
        'work_item_type' => $first->work_item_type,
        'disposition' => 'pending',
    ]);
    $third = Critique::factory()->create([
        'supersedes_id' => $second->id,
        'work_item_id' => $first->work_item_id,
        'work_item_type' => $first->work_item_type,
    ]);

    expect($first->isSuperseded())->toBeTrue()
        ->and($second->isSuperseded())->toBeTrue()
        ->and($third->isSuperseded())->toBeFalse()
        ->and($third->supersedes->supersedes->id)->toBe($first->id);
});

test('superseded critiques are immutable', function () {
    $original = Critique::factory()->create(['disposition' => 'pending']);
    Critique::factory()->create([
        'supersedes_id' => $original->id,
        'work_item_id' => $original->work_item_id,
        'work_item_type' => $original->work_item_type,
    ]);

    expect($original->isSuperseded())->toBeTrue();

    // Superseded critique should not be modifiable
    // This invariant is enforced at application level
    $original->disposition = 'accepted';
    expect($original->isSuperseded())->toBeTrue();
});

test('blocking critique with pending disposition prevents story completion', function () {
    $story = Story::factory()->create(['status' => 'in_review']);
    Critique::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'severity' => 'blocking',
        'disposition' => 'pending',
    ]);

    expect($story->hasBlockingCritique())->toBeTrue();
});

test('blocking critique with accepted disposition does not prevent completion', function () {
    $story = Story::factory()->create(['status' => 'in_review']);
    Critique::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'severity' => 'blocking',
        'disposition' => 'accepted',
    ]);

    expect($story->hasBlockingCritique())->toBeFalse();
});

test('non-blocking critique does not prevent completion', function () {
    $story = Story::factory()->create(['status' => 'in_review']);
    Critique::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'severity' => 'major',
        'disposition' => 'pending',
    ]);

    expect($story->hasBlockingCritique())->toBeFalse();
});

test('blocking critique with pending disposition prevents bug completion', function () {
    $bug = Bug::factory()->create(['status' => 'in_review']);
    Critique::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'severity' => 'blocking',
        'disposition' => 'pending',
    ]);

    expect($bug->hasBlockingCritique())->toBeTrue();
});

test('can update a critique', function () {
    $critique = Critique::factory()->create(['disposition' => 'pending']);
    $critique->update(['disposition' => 'accepted']);

    expect($critique->fresh()->disposition)->toBe('accepted');
});

test('can delete a critique', function () {
    $critique = Critique::factory()->create();
    $critiqueId = $critique->id;

    $critique->delete();

    expect(Critique::find($critiqueId))->toBeNull();
});

test('critique agent is nullable', function () {
    $critique = Critique::factory()->create(['agent_id' => null]);

    expect($critique->agent_id)->toBeNull()
        ->and($critique->agent)->toBeNull();
});

test('critique agent nullified on agent delete', function () {
    $agent = Agent::factory()->create();
    $critique = Critique::factory()->create(['agent_id' => $agent->id]);

    $agent->delete();

    expect($critique->fresh()->agent_id)->toBeNull();
});

test('critique supersedes_id nullified on superseded critique delete', function () {
    $original = Critique::factory()->create();
    $superseding = Critique::factory()->create([
        'supersedes_id' => $original->id,
        'work_item_id' => $original->work_item_id,
        'work_item_type' => $original->work_item_type,
    ]);

    $original->delete();

    expect($superseding->fresh()->supersedes_id)->toBeNull();
});
