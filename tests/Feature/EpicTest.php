<?php

use App\Models\Epic;
use App\Models\Project;

test('can create an epic', function () {
    $epic = Epic::factory()->create();

    expect($epic)->toBeInstanceOf(Epic::class)
        ->and($epic->title)->not->toBeEmpty()
        ->and($epic->status)->toBe('open')
        ->and($epic->priority)->toBeInt();

    $this->assertDatabaseHas('epics', [
        'id' => $epic->id,
        'title' => $epic->title,
    ]);
});

test('epic belongs to project', function () {
    $project = Project::factory()->create();
    $epic = Epic::factory()->create(['project_id' => $project->id]);

    expect($epic->project)->toBeInstanceOf(Project::class)
        ->and($epic->project->id)->toBe($project->id);
});

test('project has many epics', function () {
    $project = Project::factory()->create();
    Epic::factory()->count(3)->create(['project_id' => $project->id]);

    expect($project->epics)->toHaveCount(3);
});

test('epic title is required', function () {
    expect(fn () => Epic::factory()->create(['title' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('epic description is nullable', function () {
    $epic = Epic::factory()->create(['description' => null]);

    expect($epic->description)->toBeNull();
});

test('epic status defaults to open', function () {
    $epic = Epic::factory()->create();

    expect($epic->fresh()->status)->toBe('open');
});

test('can update an epic', function () {
    $epic = Epic::factory()->create();

    $epic->update([
        'title' => 'Updated Epic',
        'status' => 'closed',
        'priority' => 5,
    ]);

    expect($epic->fresh())
        ->title->toBe('Updated Epic')
        ->status->toBe('closed')
        ->priority->toBe(5);
});

test('can delete an epic', function () {
    $epic = Epic::factory()->create();

    $epic->delete();

    $this->assertDatabaseMissing('epics', ['id' => $epic->id]);
});

test('cascade deletes epics when project deleted', function () {
    $project = Project::factory()->create();
    $epic = Epic::factory()->create(['project_id' => $project->id]);

    $project->delete();

    $this->assertDatabaseMissing('epics', ['id' => $epic->id]);
});

test('can list epics', function () {
    Epic::factory()->count(3)->create();

    expect(Epic::count())->toBe(3);
});
