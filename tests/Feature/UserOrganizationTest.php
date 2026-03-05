<?php

use App\Models\Organization;
use App\Models\User;

test('user can be associated with an organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $user->organizations()->attach($organization, ['role' => 'member']);

    expect($user->organizations)->toHaveCount(1);
    expect($user->organizations->first()->id)->toBe($organization->id);
    expect($user->organizations->first()->pivot->role)->toBe('member');
});

test('organization can have multiple users', function () {
    $organization = Organization::factory()->create();
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        $user->organizations()->attach($organization, ['role' => 'member']);
    }

    expect($organization->users)->toHaveCount(3);
});

test('user can belong to multiple organizations', function () {
    $user = User::factory()->create();
    $organizations = Organization::factory()->count(3)->create();

    foreach ($organizations as $org) {
        $user->organizations()->attach($org, ['role' => 'member']);
    }

    expect($user->organizations)->toHaveCount(3);
});

test('user current organization returns first organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $user->organizations()->attach($organization, ['role' => 'admin']);

    $current = $user->currentOrganization();

    expect($current)->not->toBeNull();
    expect($current->id)->toBe($organization->id);
});

test('user current organization returns null when no organizations', function () {
    $user = User::factory()->create();

    expect($user->currentOrganization())->toBeNull();
});

test('pivot table stores role', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $user->organizations()->attach($organization, ['role' => 'admin']);

    expect($user->organizations->first()->pivot->role)->toBe('admin');
});

test('user factory withOrganization state creates association', function () {
    $user = User::factory()->withOrganization()->create();

    expect($user->organizations)->toHaveCount(1);
    expect($user->organizations->first()->pivot->role)->toBe('member');
});

test('user factory withOrganization state accepts custom organization and role', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization, 'admin')->create();

    expect($user->organizations)->toHaveCount(1);
    expect($user->organizations->first()->id)->toBe($organization->id);
    expect($user->organizations->first()->pivot->role)->toBe('admin');
});

test('pivot table has timestamps', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $user->organizations()->attach($organization, ['role' => 'member']);

    $pivot = $user->organizations->first()->pivot;

    expect($pivot->created_at)->not->toBeNull();
    expect($pivot->updated_at)->not->toBeNull();
});
