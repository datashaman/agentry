<?php

use App\Models\Agent;
use App\Models\Story;
use App\Models\Subtask;
use App\Models\Task;

test('can create a task', function () {
    $task = Task::factory()->create();

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task->title)->not->toBeEmpty()
        ->and($task->status)->toBe('pending')
        ->and($task->position)->toBeInt();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => $task->title,
    ]);
});

test('task belongs to story', function () {
    $story = Story::factory()->create();
    $task = Task::factory()->create(['story_id' => $story->id]);

    expect($task->story)->toBeInstanceOf(Story::class)
        ->and($task->story->id)->toBe($story->id);
});

test('story has many tasks', function () {
    $story = Story::factory()->create();
    Task::factory()->count(3)->create(['story_id' => $story->id]);

    expect($story->tasks)->toHaveCount(3);
});

test('task optionally assignable to agent', function () {
    $taskWithout = Task::factory()->create(['assigned_agent_id' => null]);
    expect($taskWithout->assignedAgent)->toBeNull();

    $agent = Agent::factory()->create();
    $taskWith = Task::factory()->create(['assigned_agent_id' => $agent->id]);
    expect($taskWith->assignedAgent)->toBeInstanceOf(Agent::class)
        ->and($taskWith->assignedAgent->id)->toBe($agent->id);
});

test('agent has many assigned tasks', function () {
    $agent = Agent::factory()->create();
    Task::factory()->count(2)->create(['assigned_agent_id' => $agent->id]);

    expect($agent->assignedTasks)->toHaveCount(2);
});

test('task type supports code, test, config', function () {
    $codeTask = Task::factory()->create(['type' => 'code']);
    $testTask = Task::factory()->create(['type' => 'test']);
    $configTask = Task::factory()->create(['type' => 'config']);

    expect($codeTask->type)->toBe('code')
        ->and($testTask->type)->toBe('test')
        ->and($configTask->type)->toBe('config');
});

test('task has many subtasks', function () {
    $task = Task::factory()->create();
    Subtask::factory()->count(3)->create(['task_id' => $task->id]);

    expect($task->subtasks)->toHaveCount(3);
});

test('subtask belongs to task', function () {
    $task = Task::factory()->create();
    $subtask = Subtask::factory()->create(['task_id' => $task->id]);

    expect($subtask->task)->toBeInstanceOf(Task::class)
        ->and($subtask->task->id)->toBe($task->id);
});

test('can create a subtask', function () {
    $subtask = Subtask::factory()->create();

    expect($subtask)->toBeInstanceOf(Subtask::class)
        ->and($subtask->title)->not->toBeEmpty()
        ->and($subtask->status)->toBe('pending')
        ->and($subtask->position)->toBeInt();

    $this->assertDatabaseHas('subtasks', [
        'id' => $subtask->id,
        'title' => $subtask->title,
    ]);
});

test('task title is required', function () {
    expect(fn () => Task::factory()->create(['title' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('subtask title is required', function () {
    expect(fn () => Subtask::factory()->create(['title' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('task nullable description', function () {
    $task = Task::factory()->create(['description' => null]);
    expect($task->description)->toBeNull();
});

test('subtask nullable description', function () {
    $subtask = Subtask::factory()->create(['description' => null]);
    expect($subtask->description)->toBeNull();
});

test('task update', function () {
    $task = Task::factory()->create();
    $task->update(['title' => 'Updated Task', 'status' => 'completed']);

    expect($task->fresh()->title)->toBe('Updated Task')
        ->and($task->fresh()->status)->toBe('completed');
});

test('subtask update', function () {
    $subtask = Subtask::factory()->create();
    $subtask->update(['title' => 'Updated Subtask', 'status' => 'completed']);

    expect($subtask->fresh()->title)->toBe('Updated Subtask')
        ->and($subtask->fresh()->status)->toBe('completed');
});

test('task delete', function () {
    $task = Task::factory()->create();
    $id = $task->id;
    $task->delete();

    $this->assertDatabaseMissing('tasks', ['id' => $id]);
});

test('subtask delete', function () {
    $subtask = Subtask::factory()->create();
    $id = $subtask->id;
    $subtask->delete();

    $this->assertDatabaseMissing('subtasks', ['id' => $id]);
});

test('cascade delete story removes tasks', function () {
    $story = Story::factory()->create();
    Task::factory()->count(2)->create(['story_id' => $story->id]);

    $story->delete();

    $this->assertDatabaseCount('tasks', 0);
});

test('cascade delete task removes subtasks', function () {
    $task = Task::factory()->create();
    Subtask::factory()->count(3)->create(['task_id' => $task->id]);

    $task->delete();

    $this->assertDatabaseCount('subtasks', 0);
});

test('full hierarchy: story -> task -> subtask', function () {
    $story = Story::factory()->create();
    $task1 = Task::factory()->create(['story_id' => $story->id, 'position' => 0]);
    $task2 = Task::factory()->create(['story_id' => $story->id, 'position' => 1]);
    Subtask::factory()->count(2)->create(['task_id' => $task1->id]);
    Subtask::factory()->count(3)->create(['task_id' => $task2->id]);

    $story->refresh();

    expect($story->tasks)->toHaveCount(2)
        ->and($story->tasks->first()->subtasks)->toHaveCount(2)
        ->and($story->tasks->last()->subtasks)->toHaveCount(3);
});

test('cascade delete story removes tasks and subtasks', function () {
    $story = Story::factory()->create();
    $task = Task::factory()->create(['story_id' => $story->id]);
    Subtask::factory()->count(2)->create(['task_id' => $task->id]);

    $story->delete();

    $this->assertDatabaseCount('tasks', 0);
    $this->assertDatabaseCount('subtasks', 0);
});

test('deleting agent nullifies assigned_agent_id on tasks', function () {
    $agent = Agent::factory()->create();
    $task = Task::factory()->create(['assigned_agent_id' => $agent->id]);

    $agent->delete();

    expect($task->fresh()->assigned_agent_id)->toBeNull();
});
