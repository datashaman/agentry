<?php

use App\Models\Dependency;
use App\Models\Story;

test('can create a dependency between two stories', function () {
    $blocker = Story::factory()->create();
    $blocked = Story::factory()->create();

    $dependency = Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    expect($dependency)->toBeInstanceOf(Dependency::class);

    $this->assertDatabaseHas('dependencies', [
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);
});

test('dependency blocker is polymorphic', function () {
    $blocker = Story::factory()->create();
    $dependency = Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
    ]);

    expect($dependency->blocker)->toBeInstanceOf(Story::class)
        ->and($dependency->blocker->id)->toBe($blocker->id);
});

test('dependency blocked is polymorphic', function () {
    $blocked = Story::factory()->create();
    $dependency = Dependency::factory()->create([
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    expect($dependency->blocked)->toBeInstanceOf(Story::class)
        ->and($dependency->blocked->id)->toBe($blocked->id);
});

test('story has blocked-by dependencies', function () {
    $blocker = Story::factory()->create();
    $blocked = Story::factory()->create();

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    expect($blocked->blockedByDependencies)->toHaveCount(1)
        ->and($blocked->blockedByDependencies->first()->blocker->id)->toBe($blocker->id);
});

test('story has blocker-for dependencies', function () {
    $blocker = Story::factory()->create();
    $blocked = Story::factory()->create();

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    expect($blocker->blockerForDependencies)->toHaveCount(1)
        ->and($blocker->blockerForDependencies->first()->blocked->id)->toBe($blocked->id);
});

test('duplicate dependency is prevented', function () {
    $blocker = Story::factory()->create();
    $blocked = Story::factory()->create();

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    expect(fn () => Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('can delete a dependency', function () {
    $dependency = Dependency::factory()->create();

    $dependency->delete();

    $this->assertDatabaseMissing('dependencies', ['id' => $dependency->id]);
});

test('story with unresolved blocker has unresolved blockers', function () {
    $blocker = Story::factory()->create(['status' => 'in_development']);
    $blocked = Story::factory()->create();

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    expect($blocked->hasUnresolvedBlockers())->toBeTrue();
});

test('story with resolved blocker has no unresolved blockers', function () {
    $blocker = Story::factory()->create(['status' => 'closed_done']);
    $blocked = Story::factory()->create();

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    expect($blocked->hasUnresolvedBlockers())->toBeFalse();
});

test('story with closed_wont_do blocker has no unresolved blockers', function () {
    $blocker = Story::factory()->create(['status' => 'closed_wont_do']);
    $blocked = Story::factory()->create();

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    expect($blocked->hasUnresolvedBlockers())->toBeFalse();
});

test('story without dependencies has no unresolved blockers', function () {
    $story = Story::factory()->create();

    expect($story->hasUnresolvedBlockers())->toBeFalse();
});

test('blocking invariant prevents transition to in_progress when unresolved', function () {
    $blocker = Story::factory()->create(['status' => 'in_development']);
    $blocked = Story::factory()->create(['status' => 'backlog']);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $blocker->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    expect($blocked->hasUnresolvedBlockers())->toBeTrue()
        ->and($blocked->status)->toBe('backlog');
});

test('multiple dependencies with mixed resolution', function () {
    $resolved = Story::factory()->create(['status' => 'closed_done']);
    $unresolved = Story::factory()->create(['status' => 'in_review']);
    $blocked = Story::factory()->create();

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $resolved->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    Dependency::factory()->create([
        'blocker_type' => Story::class,
        'blocker_id' => $unresolved->id,
        'blocked_type' => Story::class,
        'blocked_id' => $blocked->id,
    ]);

    expect($blocked->hasUnresolvedBlockers())->toBeTrue();
});
