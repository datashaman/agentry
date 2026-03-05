<?php

use App\Models\Agent;
use App\Models\Epic;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Story;
use Carbon\CarbonImmutable;

test('can create a story', function () {
    $story = Story::factory()->create();

    expect($story)->toBeInstanceOf(Story::class)
        ->and($story->title)->not->toBeEmpty()
        ->and($story->status)->toBe('backlog')
        ->and($story->priority)->toBeInt()
        ->and($story->spec_revision_count)->toBe(0)
        ->and($story->substantial_change)->toBeFalse()
        ->and($story->dev_iteration_count)->toBe(0);

    $this->assertDatabaseHas('stories', [
        'id' => $story->id,
        'title' => $story->title,
    ]);
});

test('story belongs to epic', function () {
    $epic = Epic::factory()->create();
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    expect($story->epic)->toBeInstanceOf(Epic::class)
        ->and($story->epic->id)->toBe($epic->id);
});

test('epic has many stories', function () {
    $epic = Epic::factory()->create();
    Story::factory()->count(3)->create(['epic_id' => $epic->id]);

    expect($epic->stories)->toHaveCount(3);
});

test('story optionally belongs to milestone', function () {
    $project = Project::factory()->create();
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    $storyWithout = Story::factory()->create(['epic_id' => $epic->id, 'milestone_id' => null]);
    $storyWith = Story::factory()->create(['epic_id' => $epic->id, 'milestone_id' => $milestone->id]);

    expect($storyWithout->milestone)->toBeNull()
        ->and($storyWith->milestone)->toBeInstanceOf(Milestone::class)
        ->and($storyWith->milestone->id)->toBe($milestone->id);
});

test('milestone has many stories', function () {
    $project = Project::factory()->create();
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    Story::factory()->count(2)->create(['epic_id' => $epic->id, 'milestone_id' => $milestone->id]);

    expect($milestone->stories)->toHaveCount(2);
});

test('story optionally assignable to agent', function () {
    $agent = Agent::factory()->create();

    $storyWithout = Story::factory()->create(['assigned_agent_id' => null]);
    $storyWith = Story::factory()->create(['assigned_agent_id' => $agent->id]);

    expect($storyWithout->assignedAgent)->toBeNull()
        ->and($storyWith->assignedAgent)->toBeInstanceOf(Agent::class)
        ->and($storyWith->assignedAgent->id)->toBe($agent->id);
});

test('agent has many assigned stories', function () {
    $agent = Agent::factory()->create();
    Story::factory()->count(2)->create(['assigned_agent_id' => $agent->id]);

    expect($agent->assignedStories)->toHaveCount(2);
});

test('story title is required', function () {
    expect(fn () => Story::factory()->create(['title' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('story description is nullable', function () {
    $story = Story::factory()->create(['description' => null]);

    expect($story->description)->toBeNull();
});

test('story status defaults to backlog', function () {
    $story = Story::factory()->create();

    expect($story->fresh()->status)->toBe('backlog');
});

test('story status supports all states', function () {
    $statuses = [
        'backlog', 'refined', 'sprint_planned', 'in_development',
        'in_review', 'staging', 'merged', 'deployed',
        'closed_done', 'closed_wont_do', 'blocked',
    ];

    foreach ($statuses as $status) {
        $story = Story::factory()->create(['status' => $status]);
        expect($story->fresh()->status)->toBe($status);
    }
});

test('story due_date is cast to date', function () {
    $story = Story::factory()->create(['due_date' => '2026-06-15']);

    expect($story->due_date)->toBeInstanceOf(CarbonImmutable::class);
});

test('story substantial_change is cast to boolean', function () {
    $story = Story::factory()->create(['substantial_change' => true]);

    expect($story->fresh()->substantial_change)->toBeTrue();
});

test('story has labels via polymorphic relationship', function () {
    $project = Project::factory()->create();
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $label = Label::factory()->create(['project_id' => $project->id]);

    $story->labels()->attach($label->id);

    expect($story->labels)->toHaveCount(1)
        ->and($story->labels->first()->id)->toBe($label->id);
});

test('can update a story', function () {
    $story = Story::factory()->create();

    $story->update([
        'title' => 'Updated Story',
        'status' => 'in_development',
        'priority' => 5,
        'story_points' => 13,
    ]);

    expect($story->fresh())
        ->title->toBe('Updated Story')
        ->status->toBe('in_development')
        ->priority->toBe(5)
        ->story_points->toBe(13);
});

test('can delete a story', function () {
    $story = Story::factory()->create();

    $story->delete();

    $this->assertDatabaseMissing('stories', ['id' => $story->id]);
});

test('cascade deletes stories when epic deleted', function () {
    $epic = Epic::factory()->create();
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $epic->delete();

    $this->assertDatabaseMissing('stories', ['id' => $story->id]);
});

test('milestone deletion nullifies story milestone_id', function () {
    $project = Project::factory()->create();
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id, 'milestone_id' => $milestone->id]);

    $milestone->delete();

    expect($story->fresh()->milestone_id)->toBeNull();
});

test('agent deletion nullifies story assigned_agent_id', function () {
    $agent = Agent::factory()->create();
    $story = Story::factory()->create(['assigned_agent_id' => $agent->id]);

    $agent->delete();

    expect($story->fresh()->assigned_agent_id)->toBeNull();
});

test('can list stories', function () {
    Story::factory()->count(3)->create();

    expect(Story::count())->toBe(3);
});
