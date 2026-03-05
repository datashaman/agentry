<?php

use App\Models\Organization;

test('can create an organization', function () {
    $organization = Organization::factory()->create();

    expect($organization)->toBeInstanceOf(Organization::class)
        ->and($organization->name)->not->toBeEmpty()
        ->and($organization->slug)->not->toBeEmpty();

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => $organization->name,
        'slug' => $organization->slug,
    ]);
});

test('organization requires a name', function () {
    $organization = Organization::factory()->make(['name' => null]);

    expect(fn () => $organization->save())->toThrow(\Illuminate\Database\QueryException::class);
});

test('organization requires a unique slug', function () {
    Organization::factory()->create(['slug' => 'test-org']);

    expect(fn () => Organization::factory()->create(['slug' => 'test-org']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('can update an organization', function () {
    $organization = Organization::factory()->create();

    $organization->update(['name' => 'Updated Name', 'slug' => 'updated-name']);

    expect($organization->fresh())
        ->name->toBe('Updated Name')
        ->slug->toBe('updated-name');
});

test('can delete an organization', function () {
    $organization = Organization::factory()->create();

    $organization->delete();

    $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
});

test('can list organizations', function () {
    Organization::factory()->count(3)->create();

    expect(Organization::count())->toBe(3);
});
