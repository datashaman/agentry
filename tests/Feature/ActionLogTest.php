<?php

use App\Models\ActionLog;
use App\Models\Agent;
use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Story;
use App\Models\Task;
use Carbon\CarbonImmutable;

test('can create an action log', function () {
    $actionLog = ActionLog::factory()->create();

    expect($actionLog)->toBeInstanceOf(ActionLog::class)
        ->and($actionLog->action)->not->toBeEmpty();
});

test('action log belongs to agent', function () {
    $agent = Agent::factory()->create();
    $actionLog = ActionLog::factory()->create(['agent_id' => $agent->id]);

    expect($actionLog->agent)->toBeInstanceOf(Agent::class)
        ->and($actionLog->agent->id)->toBe($agent->id);
});

test('agent has many action logs', function () {
    $agent = Agent::factory()->create();
    ActionLog::factory()->count(3)->create(['agent_id' => $agent->id]);

    expect($agent->actionLogs)->toHaveCount(3);
});

test('action log polymorphically belongs to story', function () {
    $story = Story::factory()->create();
    $actionLog = ActionLog::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($actionLog->workItem)->toBeInstanceOf(Story::class)
        ->and($actionLog->workItem->id)->toBe($story->id);
});

test('story has many action logs', function () {
    $story = Story::factory()->create();
    ActionLog::factory()->count(3)->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($story->actionLogs)->toHaveCount(3);
});

test('action log polymorphically belongs to task', function () {
    $task = Task::factory()->create();
    $actionLog = ActionLog::factory()->create([
        'work_item_id' => $task->id,
        'work_item_type' => Task::class,
    ]);

    expect($actionLog->workItem)->toBeInstanceOf(Task::class)
        ->and($actionLog->workItem->id)->toBe($task->id);
});

test('task has many action logs', function () {
    $task = Task::factory()->create();
    ActionLog::factory()->count(3)->create([
        'work_item_id' => $task->id,
        'work_item_type' => Task::class,
    ]);

    expect($task->actionLogs)->toHaveCount(3);
});

test('action log polymorphically belongs to bug', function () {
    $bug = Bug::factory()->create();
    $actionLog = ActionLog::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($actionLog->workItem)->toBeInstanceOf(Bug::class)
        ->and($actionLog->workItem->id)->toBe($bug->id);
});

test('bug has many action logs', function () {
    $bug = Bug::factory()->create();
    ActionLog::factory()->count(3)->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($bug->actionLogs)->toHaveCount(3);
});

test('action log polymorphically belongs to ops request', function () {
    $opsRequest = OpsRequest::factory()->create();
    $actionLog = ActionLog::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($actionLog->workItem)->toBeInstanceOf(OpsRequest::class)
        ->and($actionLog->workItem->id)->toBe($opsRequest->id);
});

test('ops request has many action logs', function () {
    $opsRequest = OpsRequest::factory()->create();
    ActionLog::factory()->count(3)->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($opsRequest->actionLogs)->toHaveCount(3);
});

test('action log agent_id is nullable', function () {
    $actionLog = ActionLog::factory()->create(['agent_id' => null]);

    expect($actionLog->agent_id)->toBeNull()
        ->and($actionLog->agent)->toBeNull();
});

test('action log work item is nullable', function () {
    $actionLog = ActionLog::factory()->create([
        'work_item_id' => null,
        'work_item_type' => null,
    ]);

    expect($actionLog->work_item_id)->toBeNull()
        ->and($actionLog->workItem)->toBeNull();
});

test('action log reasoning is nullable', function () {
    $actionLog = ActionLog::factory()->create(['reasoning' => null]);

    expect($actionLog->reasoning)->toBeNull();
});

test('action log timestamp is cast to datetime', function () {
    $actionLog = ActionLog::factory()->create(['timestamp' => '2026-03-01 10:00:00']);

    expect($actionLog->timestamp)->toBeInstanceOf(CarbonImmutable::class);
});

test('action log timestamp is nullable', function () {
    $actionLog = ActionLog::factory()->create(['timestamp' => null]);

    expect($actionLog->timestamp)->toBeNull();
});

test('can update an action log', function () {
    $actionLog = ActionLog::factory()->create(['action' => 'created_branch']);
    $actionLog->update(['action' => 'committed_code', 'reasoning' => 'Updated reasoning']);

    expect($actionLog->fresh()->action)->toBe('committed_code')
        ->and($actionLog->fresh()->reasoning)->toBe('Updated reasoning');
});

test('can delete an action log', function () {
    $actionLog = ActionLog::factory()->create();
    $actionLogId = $actionLog->id;

    $actionLog->delete();

    expect(ActionLog::find($actionLogId))->toBeNull();
});

test('action log agent nullified on agent delete', function () {
    $agent = Agent::factory()->create();
    $actionLog = ActionLog::factory()->create(['agent_id' => $agent->id]);

    $agent->delete();

    expect($actionLog->fresh()->agent_id)->toBeNull();
});

test('can query action logs by agent', function () {
    $agent = Agent::factory()->create();
    ActionLog::factory()->count(5)->create(['agent_id' => $agent->id]);
    ActionLog::factory()->count(3)->create();

    expect(ActionLog::where('agent_id', $agent->id)->count())->toBe(5);
});

test('can query action logs by work item', function () {
    $story = Story::factory()->create();
    ActionLog::factory()->count(4)->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);
    ActionLog::factory()->count(2)->create();

    expect(ActionLog::where('work_item_id', $story->id)
        ->where('work_item_type', Story::class)
        ->count())->toBe(4);
});
