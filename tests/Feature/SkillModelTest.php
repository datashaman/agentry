<?php

use App\Models\Organization;
use App\Models\Skill;

test('can create skill with required fields', function () {
    $organization = Organization::factory()->create();

    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Laravel Development',
        'slug' => 'laravel-development',
        'description' => 'Laravel best practices',
        'content' => 'Use Laravel conventions.',
    ]);

    expect($skill->organization_id)->toBe($organization->id);
    expect($skill->name)->toBe('Laravel Development');
    expect($skill->slug)->toBe('laravel-development');
    expect($skill->description)->toBe('Laravel best practices');
    expect($skill->content)->toBe('Use Laravel conventions.');
});

test('same slug is allowed in different organizations', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $skillA = Skill::factory()->create([
        'organization_id' => $orgA->id,
        'slug' => 'laravel',
    ]);

    $skillB = Skill::factory()->create([
        'organization_id' => $orgB->id,
        'slug' => 'laravel',
    ]);

    expect($skillA->slug)->toBe($skillB->slug);
    expect($skillA->organization_id)->not->toBe($skillB->organization_id);
});

test('same slug in same organization fails with unique constraint', function () {
    $organization = Organization::factory()->create();

    Skill::factory()->create([
        'organization_id' => $organization->id,
        'slug' => 'laravel',
    ]);

    expect(fn () => Skill::factory()->create([
        'organization_id' => $organization->id,
        'slug' => 'laravel',
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
