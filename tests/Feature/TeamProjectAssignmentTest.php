<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;

test('can assign a team to a project', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $project->teams()->attach($team);

    expect($project->teams)->toHaveCount(1)
        ->and($project->teams->first()->id)->toBe($team->id);
});

test('team belongs to many projects', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $projects = Project::factory()->count(3)->create(['organization_id' => $organization->id]);

    $team->projects()->attach($projects);

    expect($team->projects)->toHaveCount(3)
        ->each->toBeInstanceOf(Project::class);
});

test('project belongs to many teams', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $teams = Team::factory()->count(3)->create(['organization_id' => $organization->id]);

    $project->teams()->attach($teams);

    expect($project->teams)->toHaveCount(3)
        ->each->toBeInstanceOf(Team::class);
});

test('pivot table has timestamps', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $project->teams()->attach($team);

    $pivot = $project->teams->first()->pivot;

    expect($pivot->created_at)->not->toBeNull()
        ->and($pivot->updated_at)->not->toBeNull();
});

test('cannot assign same team to same project twice', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $project->teams()->attach($team);

    expect(fn () => $project->teams()->attach($team))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('can detach a team from a project', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $project->teams()->attach($team);
    $project->teams()->detach($team);

    expect($project->fresh()->teams)->toHaveCount(0);
});

test('deleting a project removes pivot records', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $project->teams()->attach($team);
    $project->delete();

    $this->assertDatabaseMissing('project_team', ['project_id' => $project->id]);
});

test('deleting a team removes pivot records', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $project->teams()->attach($team);
    $team->delete();

    $this->assertDatabaseMissing('project_team', ['team_id' => $team->id]);
});

test('project hasTeam returns true for assigned team', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $project->teams()->attach($team);

    expect($project->hasTeam($team))->toBeTrue();
});

test('project hasTeam returns false for unassigned team', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    expect($project->hasTeam($team))->toBeFalse();
});
