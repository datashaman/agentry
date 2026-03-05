<?php

use App\Models\Epic;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('story detail shows tasks ordered by position', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    Task::factory()->create(['story_id' => $story->id, 'title' => 'Second', 'position' => 10]);
    Task::factory()->create(['story_id' => $story->id, 'title' => 'First', 'position' => 0]);
    Task::factory()->create(['story_id' => $story->id, 'title' => 'Third', 'position' => 20]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();

    $pos = strpos($response->getContent(), 'First');
    $posSecond = strpos($response->getContent(), 'Second');
    $posThird = strpos($response->getContent(), 'Third');
    expect($pos)->toBeLessThan($posSecond)
        ->and($posSecond)->toBeLessThan($posThird);
});

test('story detail shows tasks with title type status and subtask count', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $task = Task::factory()->create([
        'story_id' => $story->id,
        'title' => 'Implement auth',
        'type' => 'code',
        'status' => 'in_progress',
    ]);
    Subtask::factory()->count(2)->create(['task_id' => $task->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Implement auth');
    $response->assertSee('code');
    $response->assertSee('in progress');
    $response->assertSee('2 subtasks');
});

test('update task status on story detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $task = Task::factory()->create(['story_id' => $story->id, 'status' => 'pending']);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('updateTaskStatus', $task->id, 'completed');

    expect($task->fresh()->status)->toBe('completed');
});

test('update subtask status on story detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $task = Task::factory()->create(['story_id' => $story->id]);
    $subtask = Subtask::factory()->create(['task_id' => $task->id, 'status' => 'pending']);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('updateSubtaskStatus', $subtask->id, 'completed');

    expect($subtask->fresh()->status)->toBe('completed');
});

test('reorder tasks with move up and move down', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $task1 = Task::factory()->create(['story_id' => $story->id, 'position' => 0]);
    $task2 = Task::factory()->create(['story_id' => $story->id, 'position' => 1]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('moveTaskDown', $task1->id);

    expect($task1->fresh()->position)->toBe(1)
        ->and($task2->fresh()->position)->toBe(0);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('moveTaskUp', $task1->id);

    expect($task1->fresh()->position)->toBe(0)
        ->and($task2->fresh()->position)->toBe(1);
});

test('reorder subtasks with move up and move down', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $task = Task::factory()->create(['story_id' => $story->id]);

    $sub1 = Subtask::factory()->create(['task_id' => $task->id, 'position' => 0]);
    $sub2 = Subtask::factory()->create(['task_id' => $task->id, 'position' => 1]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('moveSubtaskDown', $sub1->id);

    expect($sub1->fresh()->position)->toBe(1)
        ->and($sub2->fresh()->position)->toBe(0);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('moveSubtaskUp', $sub1->id);

    expect($sub1->fresh()->position)->toBe(0)
        ->and($sub2->fresh()->position)->toBe(1);
});
