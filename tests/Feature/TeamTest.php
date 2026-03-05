<?php

use App\Models\Organization;
use App\Models\Team;

test('can create a team', function () {
    $team = Team::factory()->create();

    expect($team)->toBeInstanceOf(Team::class)
        ->and($team->name)->not->toBeEmpty()
        ->and($team->slug)->not->toBeEmpty()
        ->and($team->organization_id)->not->toBeNull();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => $team->name,
        'slug' => $team->slug,
    ]);
});

test('team belongs to organization', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);

    expect($team->organization)->toBeInstanceOf(Organization::class)
        ->and($team->organization->id)->toBe($organization->id);
});

test('organization has many teams', function () {
    $organization = Organization::factory()->create();
    Team::factory()->count(3)->create(['organization_id' => $organization->id]);

    expect($organization->teams)->toHaveCount(3)
        ->each->toBeInstanceOf(Team::class);
});

test('team requires a name', function () {
    $team = Team::factory()->make(['name' => null]);

    expect(fn () => $team->save())->toThrow(\Illuminate\Database\QueryException::class);
});

test('team requires unique slug within organization', function () {
    $organization = Organization::factory()->create();
    Team::factory()->create(['organization_id' => $organization->id, 'slug' => 'dev-team']);

    expect(fn () => Team::factory()->create(['organization_id' => $organization->id, 'slug' => 'dev-team']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('same slug allowed in different organizations', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    $team1 = Team::factory()->create(['organization_id' => $org1->id, 'slug' => 'dev-team']);
    $team2 = Team::factory()->create(['organization_id' => $org2->id, 'slug' => 'dev-team']);

    expect($team1->slug)->toBe($team2->slug)
        ->and($team1->organization_id)->not->toBe($team2->organization_id);
});

test('can update a team', function () {
    $team = Team::factory()->create();

    $team->update(['name' => 'Updated Team', 'slug' => 'updated-team']);

    expect($team->fresh())
        ->name->toBe('Updated Team')
        ->slug->toBe('updated-team');
});

test('can delete a team', function () {
    $team = Team::factory()->create();

    $team->delete();

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

test('deleting organization cascades to teams', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);

    $organization->delete();

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});
