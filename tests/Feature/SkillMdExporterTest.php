<?php

use App\Models\Organization;
use App\Models\Skill;
use App\Services\SkillMdExporter;
use App\Services\SkillMdParser;

test('export generates valid SKILL.md', function () {
    $organization = Organization::factory()->create();
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Laravel Best Practices',
        'slug' => 'laravel-best-practices',
        'description' => 'Laravel coding conventions.',
        'content' => "# Laravel\n\nUse Eloquent and Blade.",
    ]);

    $exporter = new SkillMdExporter;
    $output = $exporter->export($skill);

    expect($output)->toContain('name: laravel-best-practices')
        ->and($output)->toContain('Laravel coding conventions.')
        ->and($output)->toContain('# Laravel')
        ->and($output)->toContain('Use Eloquent and Blade.');
});

test('export includes optional frontmatter metadata', function () {
    $organization = Organization::factory()->create();
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'slug' => 'test-skill',
        'description' => 'Test skill.',
        'content' => 'Body.',
        'frontmatter_metadata' => [
            'license' => 'MIT',
            'allowed-tools' => ['bash'],
        ],
    ]);

    $exporter = new SkillMdExporter;
    $output = $exporter->export($skill);

    expect($output)->toContain('license: MIT')
        ->and($output)->toContain('allowed-tools');
});

test('roundtrip: parse exported SKILL.md produces equivalent data', function () {
    $organization = Organization::factory()->create();
    $skill = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Roundtrip Test',
        'slug' => 'roundtrip-test',
        'description' => 'Verifies roundtrip fidelity.',
        'content' => "# Instructions\n\nDo the thing.",
        'frontmatter_metadata' => ['license' => 'Apache-2.0'],
    ]);

    $exporter = new SkillMdExporter;
    $parser = new SkillMdParser;

    $exported = $exporter->export($skill);
    $parsed = $parser->parse($exported);

    expect($parsed['valid'])->toBeTrue()
        ->and($parsed['name'])->toBe('roundtrip-test')
        ->and($parsed['description'])->toBe('Verifies roundtrip fidelity.')
        ->and($parsed['license'])->toBe('Apache-2.0')
        ->and($parsed['body'])->toContain('# Instructions')
        ->and($parsed['body'])->toContain('Do the thing.');
});
