<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Story;

test('can create a bug', function () {
    $bug = Bug::factory()->create();

    expect($bug)->toBeInstanceOf(Bug::class)
        ->and($bug->title)->not->toBeEmpty()
        ->and($bug->status)->toBe('new')
        ->and($bug->priority)->toBeInt();

    $this->assertDatabaseHas('bugs', [
        'id' => $bug->id,
        'title' => $bug->title,
    ]);
});

test('bug belongs to project', function () {
    $project = Project::factory()->create();
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    expect($bug->project)->toBeInstanceOf(Project::class)
        ->and($bug->project->id)->toBe($project->id);
});

test('project has many bugs', function () {
    $project = Project::factory()->create();
    Bug::factory()->count(3)->create(['project_id' => $project->id]);

    expect($project->bugs)->toHaveCount(3);
});

test('bug optionally linked to story', function () {
    $story = Story::factory()->create();

    $bugWithout = Bug::factory()->create(['linked_story_id' => null]);
    $bugWith = Bug::factory()->create(['linked_story_id' => $story->id]);

    expect($bugWithout->linkedStory)->toBeNull()
        ->and($bugWith->linkedStory)->toBeInstanceOf(Story::class)
        ->and($bugWith->linkedStory->id)->toBe($story->id);
});

test('story has many bugs via linked_story_id', function () {
    $story = Story::factory()->create();
    Bug::factory()->count(2)->create(['linked_story_id' => $story->id]);

    expect($story->bugs)->toHaveCount(2);
});

test('bug optionally belongs to milestone', function () {
    $project = Project::factory()->create();
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    $bugWithout = Bug::factory()->create(['project_id' => $project->id, 'milestone_id' => null]);
    $bugWith = Bug::factory()->create(['project_id' => $project->id, 'milestone_id' => $milestone->id]);

    expect($bugWithout->milestone)->toBeNull()
        ->and($bugWith->milestone)->toBeInstanceOf(Milestone::class)
        ->and($bugWith->milestone->id)->toBe($milestone->id);
});

test('milestone has many bugs', function () {
    $project = Project::factory()->create();
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    Bug::factory()->count(2)->create(['project_id' => $project->id, 'milestone_id' => $milestone->id]);

    expect($milestone->bugs)->toHaveCount(2);
});

test('bug optionally assignable to agent', function () {
    $agent = Agent::factory()->create();

    $bugWithout = Bug::factory()->create(['assigned_agent_id' => null]);
    $bugWith = Bug::factory()->create(['assigned_agent_id' => $agent->id]);

    expect($bugWithout->assignedAgent)->toBeNull()
        ->and($bugWith->assignedAgent)->toBeInstanceOf(Agent::class)
        ->and($bugWith->assignedAgent->id)->toBe($agent->id);
});

test('agent has many assigned bugs', function () {
    $agent = Agent::factory()->create();
    Bug::factory()->count(2)->create(['assigned_agent_id' => $agent->id]);

    expect($agent->assignedBugs)->toHaveCount(2);
});

test('bug title is required', function () {
    expect(fn () => Bug::factory()->create(['title' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('bug description is nullable', function () {
    $bug = Bug::factory()->create(['description' => null]);

    expect($bug->description)->toBeNull();
});

test('bug status defaults to new', function () {
    $bug = Bug::factory()->create();

    expect($bug->fresh()->status)->toBe('new');
});

test('bug status supports all states', function () {
    $statuses = [
        'new', 'triaged', 'in_progress', 'in_review', 'verified', 'released',
        'closed_fixed', 'closed_duplicate', 'closed_cant_reproduce', 'blocked',
    ];

    foreach ($statuses as $status) {
        $bug = Bug::factory()->create(['status' => $status]);
        expect($bug->fresh()->status)->toBe($status);
    }
});

test('bug severity supports all levels', function () {
    $severities = ['critical', 'major', 'minor', 'trivial'];

    foreach ($severities as $severity) {
        $bug = Bug::factory()->create(['severity' => $severity]);
        expect($bug->fresh()->severity)->toBe($severity);
    }
});

test('bug has labels via polymorphic relationship', function () {
    $project = Project::factory()->create();
    $bug = Bug::factory()->create(['project_id' => $project->id]);
    $label = Label::factory()->create(['project_id' => $project->id]);

    $bug->labels()->attach($label->id);

    expect($bug->labels)->toHaveCount(1)
        ->and($bug->labels->first()->id)->toBe($label->id);
});

test('label morphedByMany bugs', function () {
    $project = Project::factory()->create();
    $label = Label::factory()->create(['project_id' => $project->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $bug->labels()->attach($label->id);

    expect($label->bugs)->toHaveCount(1)
        ->and($label->bugs->first()->id)->toBe($bug->id);
});

test('can update a bug', function () {
    $bug = Bug::factory()->create();

    $bug->update([
        'title' => 'Updated Bug',
        'status' => 'triaged',
        'severity' => 'critical',
        'priority' => 1,
    ]);

    expect($bug->fresh())
        ->title->toBe('Updated Bug')
        ->status->toBe('triaged')
        ->severity->toBe('critical')
        ->priority->toBe(1);
});

test('can delete a bug', function () {
    $bug = Bug::factory()->create();

    $bug->delete();

    $this->assertDatabaseMissing('bugs', ['id' => $bug->id]);
});

test('cascade deletes bugs when project deleted', function () {
    $project = Project::factory()->create();
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $project->delete();

    $this->assertDatabaseMissing('bugs', ['id' => $bug->id]);
});

test('story deletion nullifies bug linked_story_id', function () {
    $story = Story::factory()->create();
    $bug = Bug::factory()->create(['linked_story_id' => $story->id]);

    $story->delete();

    expect($bug->fresh()->linked_story_id)->toBeNull();
});

test('milestone deletion nullifies bug milestone_id', function () {
    $project = Project::factory()->create();
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id, 'milestone_id' => $milestone->id]);

    $milestone->delete();

    expect($bug->fresh()->milestone_id)->toBeNull();
});

test('agent deletion nullifies bug assigned_agent_id', function () {
    $agent = Agent::factory()->create();
    $bug = Bug::factory()->create(['assigned_agent_id' => $agent->id]);

    $agent->delete();

    expect($bug->fresh()->assigned_agent_id)->toBeNull();
});

test('can list bugs', function () {
    Bug::factory()->count(3)->create();

    expect(Bug::count())->toBe(3);
});
