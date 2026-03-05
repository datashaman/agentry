<?php

use App\Models\Milestone;
use App\Models\Project;

test('can create a milestone', function () {
    $milestone = Milestone::factory()->create();

    expect($milestone)->toBeInstanceOf(Milestone::class)
        ->and($milestone->title)->not->toBeEmpty()
        ->and($milestone->status)->toBe('open');

    $this->assertDatabaseHas('milestones', [
        'id' => $milestone->id,
        'title' => $milestone->title,
    ]);
});

test('milestone belongs to project', function () {
    $project = Project::factory()->create();
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    expect($milestone->project)->toBeInstanceOf(Project::class)
        ->and($milestone->project->id)->toBe($project->id);
});

test('project has many milestones', function () {
    $project = Project::factory()->create();
    Milestone::factory()->count(3)->create(['project_id' => $project->id]);

    expect($project->milestones)->toHaveCount(3);
});

test('milestone title is required', function () {
    expect(fn () => Milestone::factory()->create(['title' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('milestone description is nullable', function () {
    $milestone = Milestone::factory()->create(['description' => null]);

    expect($milestone->description)->toBeNull();
});

test('milestone due_date is nullable', function () {
    $milestone = Milestone::factory()->create(['due_date' => null]);

    expect($milestone->due_date)->toBeNull();
});

test('milestone due_date is cast to date', function () {
    $milestone = Milestone::factory()->create(['due_date' => '2026-06-15']);

    expect($milestone->due_date)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($milestone->due_date->format('Y-m-d'))->toBe('2026-06-15');
});

test('milestone status defaults to open', function () {
    $milestone = Milestone::factory()->create();

    expect($milestone->fresh()->status)->toBe('open');
});

test('can update a milestone', function () {
    $milestone = Milestone::factory()->create();

    $milestone->update([
        'title' => 'Updated Milestone',
        'status' => 'closed',
        'due_date' => '2026-12-31',
    ]);

    expect($milestone->fresh())
        ->title->toBe('Updated Milestone')
        ->status->toBe('closed')
        ->due_date->format('Y-m-d')->toBe('2026-12-31');
});

test('can delete a milestone', function () {
    $milestone = Milestone::factory()->create();

    $milestone->delete();

    $this->assertDatabaseMissing('milestones', ['id' => $milestone->id]);
});

test('cascade deletes milestones when project deleted', function () {
    $project = Project::factory()->create();
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    $project->delete();

    $this->assertDatabaseMissing('milestones', ['id' => $milestone->id]);
});

test('can list milestones', function () {
    Milestone::factory()->count(3)->create();

    expect(Milestone::count())->toBe(3);
});

test('seeder creates default milestone', function () {
    $project = Project::factory()->create();
    $this->seed(\Database\Seeders\MilestoneSeeder::class);

    $this->assertDatabaseHas('milestones', ['title' => 'v1.0 - Core Platform']);
});
