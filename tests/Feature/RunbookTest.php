<?php

use App\Models\OpsRequest;
use App\Models\Runbook;
use App\Models\RunbookStep;

test('can create a runbook', function () {
    $opsRequest = OpsRequest::factory()->create();
    $runbook = Runbook::create([
        'ops_request_id' => $opsRequest->id,
        'title' => 'Deploy v2.0',
        'description' => 'Steps to deploy version 2.0',
        'status' => 'draft',
    ]);

    expect($runbook)->toBeInstanceOf(Runbook::class)
        ->and($runbook->title)->toBe('Deploy v2.0')
        ->and($runbook->description)->toBe('Steps to deploy version 2.0')
        ->and($runbook->status)->toBe('draft');
});

test('runbook belongs to ops request', function () {
    $opsRequest = OpsRequest::factory()->create();
    $runbook = Runbook::factory()->create(['ops_request_id' => $opsRequest->id]);

    expect($runbook->opsRequest->id)->toBe($opsRequest->id);
});

test('ops request has many runbooks', function () {
    $opsRequest = OpsRequest::factory()->create();
    Runbook::factory()->count(3)->create(['ops_request_id' => $opsRequest->id]);

    expect($opsRequest->runbooks)->toHaveCount(3);
});

test('runbook status defaults to draft', function () {
    $opsRequest = OpsRequest::factory()->create();
    $runbook = Runbook::create([
        'ops_request_id' => $opsRequest->id,
        'title' => 'Test Runbook',
    ]);
    $runbook->refresh();

    expect($runbook->status)->toBe('draft');
});

test('runbook supports all status values', function (string $status) {
    $runbook = Runbook::factory()->create(['status' => $status]);

    expect($runbook->status)->toBe($status);
})->with(['draft', 'approved', 'executing', 'completed', 'failed']);

test('runbook description is nullable', function () {
    $runbook = Runbook::factory()->create(['description' => null]);

    expect($runbook->description)->toBeNull();
});

test('runbook title is required', function () {
    $runbook = Runbook::factory()->create(['title' => 'Required Title']);

    expect($runbook->title)->toBe('Required Title');
});

test('can update a runbook', function () {
    $runbook = Runbook::factory()->create(['status' => 'draft']);
    $runbook->update(['status' => 'approved', 'title' => 'Updated Title']);

    expect($runbook->fresh()->status)->toBe('approved')
        ->and($runbook->fresh()->title)->toBe('Updated Title');
});

test('can delete a runbook', function () {
    $runbook = Runbook::factory()->create();
    $runbookId = $runbook->id;
    $runbook->delete();

    expect(Runbook::find($runbookId))->toBeNull();
});

test('deleting ops request cascades to runbooks', function () {
    $opsRequest = OpsRequest::factory()->create();
    Runbook::factory()->count(2)->create(['ops_request_id' => $opsRequest->id]);

    $opsRequest->delete();

    expect(Runbook::count())->toBe(0);
});

test('can create a runbook step', function () {
    $runbook = Runbook::factory()->create();
    $step = RunbookStep::create([
        'runbook_id' => $runbook->id,
        'position' => 1,
        'instruction' => 'Run database backup',
        'status' => 'pending',
    ]);

    expect($step)->toBeInstanceOf(RunbookStep::class)
        ->and($step->instruction)->toBe('Run database backup')
        ->and($step->position)->toBe(1)
        ->and($step->status)->toBe('pending');
});

test('runbook step belongs to runbook', function () {
    $runbook = Runbook::factory()->create();
    $step = RunbookStep::factory()->create(['runbook_id' => $runbook->id]);

    expect($step->runbook->id)->toBe($runbook->id);
});

test('runbook has many steps', function () {
    $runbook = Runbook::factory()->create();
    RunbookStep::factory()->count(5)->create(['runbook_id' => $runbook->id]);

    expect($runbook->steps)->toHaveCount(5);
});

test('runbook steps are ordered by position', function () {
    $runbook = Runbook::factory()->create();
    RunbookStep::factory()->create(['runbook_id' => $runbook->id, 'position' => 3, 'instruction' => 'Third']);
    RunbookStep::factory()->create(['runbook_id' => $runbook->id, 'position' => 1, 'instruction' => 'First']);
    RunbookStep::factory()->create(['runbook_id' => $runbook->id, 'position' => 2, 'instruction' => 'Second']);

    $steps = $runbook->steps;

    expect($steps[0]->instruction)->toBe('First')
        ->and($steps[1]->instruction)->toBe('Second')
        ->and($steps[2]->instruction)->toBe('Third');
});

test('runbook step status defaults to pending', function () {
    $runbook = Runbook::factory()->create();
    $step = RunbookStep::create([
        'runbook_id' => $runbook->id,
        'position' => 1,
        'instruction' => 'Test step',
    ]);
    $step->refresh();

    expect($step->status)->toBe('pending');
});

test('runbook step supports all status values', function (string $status) {
    $step = RunbookStep::factory()->create(['status' => $status]);

    expect($step->status)->toBe($status);
})->with(['pending', 'executing', 'completed', 'failed', 'skipped']);

test('runbook step executed_by is nullable', function () {
    $step = RunbookStep::factory()->create(['executed_by' => null, 'executed_at' => null]);

    expect($step->executed_by)->toBeNull()
        ->and($step->executed_at)->toBeNull();
});

test('runbook step executed_at is cast to datetime', function () {
    $step = RunbookStep::factory()->create(['executed_at' => '2026-03-05 10:00:00']);

    expect($step->executed_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
});

test('can update a runbook step', function () {
    $step = RunbookStep::factory()->create(['status' => 'pending']);
    $step->update([
        'status' => 'completed',
        'executed_by' => 'admin',
        'executed_at' => now(),
    ]);

    expect($step->fresh()->status)->toBe('completed')
        ->and($step->fresh()->executed_by)->toBe('admin')
        ->and($step->fresh()->executed_at)->not->toBeNull();
});

test('can delete a runbook step', function () {
    $step = RunbookStep::factory()->create();
    $stepId = $step->id;
    $step->delete();

    expect(RunbookStep::find($stepId))->toBeNull();
});

test('deleting runbook cascades to steps', function () {
    $runbook = Runbook::factory()->create();
    RunbookStep::factory()->count(3)->create(['runbook_id' => $runbook->id]);

    $runbook->delete();

    expect(RunbookStep::count())->toBe(0);
});

test('deleting ops request cascades through runbook to steps', function () {
    $opsRequest = OpsRequest::factory()->create();
    $runbook = Runbook::factory()->create(['ops_request_id' => $opsRequest->id]);
    RunbookStep::factory()->count(3)->create(['runbook_id' => $runbook->id]);

    $opsRequest->delete();

    expect(Runbook::count())->toBe(0)
        ->and(RunbookStep::count())->toBe(0);
});

test('can list runbooks by ops request', function () {
    $opsRequest1 = OpsRequest::factory()->create();
    $opsRequest2 = OpsRequest::factory()->create();
    Runbook::factory()->count(2)->create(['ops_request_id' => $opsRequest1->id]);
    Runbook::factory()->count(3)->create(['ops_request_id' => $opsRequest2->id]);

    expect($opsRequest1->runbooks)->toHaveCount(2)
        ->and($opsRequest2->runbooks)->toHaveCount(3);
});

test('runbook factory creates valid model', function () {
    $runbook = Runbook::factory()->create();

    expect($runbook->title)->not->toBeEmpty()
        ->and($runbook->ops_request_id)->not->toBeNull()
        ->and($runbook->opsRequest)->toBeInstanceOf(OpsRequest::class);
});

test('runbook step factory creates valid model', function () {
    $step = RunbookStep::factory()->create();

    expect($step->instruction)->not->toBeEmpty()
        ->and($step->runbook_id)->not->toBeNull()
        ->and($step->runbook)->toBeInstanceOf(Runbook::class);
});
