<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Skill;
use App\Services\GitHubAppService;
use App\Services\SkillImportService;
use App\Services\SkillMdParser;

test('discoverSkillsInRepo finds SKILL.md files', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $mockGitHub = Mockery::mock(GitHubAppService::class);
    $mockGitHub->shouldReceive('getTree')->once()->andReturn([
        ['type' => 'blob', 'path' => '.agents/skills/laravel/SKILL.md', 'sha' => 'abc123'],
        ['type' => 'blob', 'path' => '.agents/skills/laravel/scripts/setup.sh', 'sha' => 'def456'],
        ['type' => 'blob', 'path' => 'README.md', 'sha' => 'xyz789'],
    ]);
    $mockGitHub->shouldReceive('getFileContent')
        ->with($repo, '.agents/skills/laravel/SKILL.md')
        ->once()
        ->andReturn("---\nname: laravel\ndescription: Laravel skill.\n---\n\nBody content.");

    $service = new SkillImportService($mockGitHub, new SkillMdParser);
    $discovered = $service->discoverSkillsInRepo($repo);

    expect($discovered)->toHaveCount(1)
        ->and($discovered[0]['parsed']['name'])->toBe('laravel')
        ->and($discovered[0]['resource_paths'])->toBe(['.agents/skills/laravel/scripts/setup.sh']);
});

test('importSkill creates a new skill', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $discovered = [
        'path' => '.agents/skills/testing/SKILL.md',
        'sha' => 'sha123',
        'resource_paths' => [],
        'parsed' => [
            'name' => 'testing',
            'description' => 'Testing skill.',
            'license' => 'MIT',
            'compatibility' => null,
            'metadata' => null,
            'allowed_tools' => null,
            'body' => 'Write tests first.',
            'valid' => true,
            'errors' => [],
        ],
    ];

    $mockGitHub = Mockery::mock(GitHubAppService::class);
    $service = new SkillImportService($mockGitHub, new SkillMdParser);
    $skill = $service->importSkill($organization, $discovered, $repo);

    expect($skill)->toBeInstanceOf(Skill::class)
        ->and($skill->slug)->toBe('testing')
        ->and($skill->source_repo_id)->toBe($repo->id)
        ->and($skill->source_path)->toBe('.agents/skills/testing/SKILL.md')
        ->and($skill->content)->toBe('Write tests first.')
        ->and($skill->frontmatter_metadata)->toBe(['license' => 'MIT']);
});

test('importSkill updates existing skill with same slug', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $existing = Skill::factory()->create([
        'organization_id' => $organization->id,
        'slug' => 'existing',
        'content' => 'Old content.',
    ]);

    $discovered = [
        'path' => '.agents/skills/existing/SKILL.md',
        'sha' => 'newsha',
        'resource_paths' => [],
        'parsed' => [
            'name' => 'existing',
            'description' => 'Updated skill.',
            'license' => null,
            'compatibility' => null,
            'metadata' => null,
            'allowed_tools' => null,
            'body' => 'New content.',
            'valid' => true,
            'errors' => [],
        ],
    ];

    $mockGitHub = Mockery::mock(GitHubAppService::class);
    $service = new SkillImportService($mockGitHub, new SkillMdParser);
    $skill = $service->importSkill($organization, $discovered, $repo);

    expect($skill->id)->toBe($existing->id)
        ->and($skill->content)->toBe('New content.');
});

test('syncSkillsFromRepo returns summary', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    $mockGitHub = Mockery::mock(GitHubAppService::class);
    $mockGitHub->shouldReceive('getTree')->once()->andReturn([
        ['type' => 'blob', 'path' => '.agents/skills/valid/SKILL.md', 'sha' => 'aaa'],
        ['type' => 'blob', 'path' => '.agents/skills/invalid/SKILL.md', 'sha' => 'bbb'],
    ]);
    $mockGitHub->shouldReceive('getFileContent')
        ->with($repo, '.agents/skills/valid/SKILL.md')
        ->andReturn("---\nname: valid\ndescription: Valid skill.\n---\n\nContent.");
    $mockGitHub->shouldReceive('getFileContent')
        ->with($repo, '.agents/skills/invalid/SKILL.md')
        ->andReturn('No frontmatter here.');

    $service = new SkillImportService($mockGitHub, new SkillMdParser);
    $result = $service->syncSkillsFromRepo($organization, $repo);

    expect($result['imported'])->toBe(1)
        ->and($result['updated'])->toBe(0)
        ->and($result['errors'])->toBe(1)
        ->and($result['skills'])->toHaveCount(1);
});
