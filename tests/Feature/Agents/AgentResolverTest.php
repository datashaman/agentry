<?php

use App\Agents\AgentResolver;
use App\Agents\ToolRegistry;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Bug;
use App\Models\Epic;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Story;
use App\Models\Team;

beforeEach(function () {
    $this->resolver = new AgentResolver(new ToolRegistry);
});

test('resolver returns merged config with agent overrides taking precedence', function () {
    $organization = Organization::factory()->create();
    $agentRole = AgentRole::factory()
        ->forOrganization($organization)
        ->create([
            'instructions' => 'You are a helpful assistant.',
            'tools' => ['web_search', 'web_fetch'],
            'default_model' => 'claude-sonnet-4',
            'default_provider' => 'anthropic',
            'default_temperature' => 0.5,
            'default_max_steps' => 10,
            'default_max_tokens' => 4096,
            'default_timeout' => 60,
        ]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
        'model' => 'claude-opus-4-6',
        'provider' => 'anthropic',
        'temperature' => 0.7,
        'max_steps' => 15,
        'max_tokens' => 8192,
        'timeout' => 120,
    ]);

    $config = $this->resolver->resolve($agent);

    expect($config['instructions'])->toBe('You are a helpful assistant.')
        ->and($config['tools'])->toBe(['web_search', 'web_fetch'])
        ->and($config['model'])->toBe('claude-opus-4-6')
        ->and($config['provider'])->toBe('anthropic')
        ->and($config['temperature'])->toBe(0.7)
        ->and($config['max_steps'])->toBe(15)
        ->and($config['max_tokens'])->toBe(8192)
        ->and($config['timeout'])->toBe(120);
});

test('resolver falls back to type defaults when agent overrides are null', function () {
    $organization = Organization::factory()->create();
    $agentRole = AgentRole::factory()
        ->forOrganization($organization)
        ->create([
            'instructions' => 'Default instructions.',
            'tools' => ['web_search'],
            'default_model' => 'claude-sonnet-4',
            'default_provider' => 'anthropic',
            'default_temperature' => 0.6,
            'default_max_steps' => 8,
            'default_max_tokens' => 2048,
            'default_timeout' => 90,
        ]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
        'model' => 'claude-opus-4-6',
        'provider' => 'anthropic',
        'temperature' => null,
        'max_steps' => null,
        'max_tokens' => null,
        'timeout' => null,
    ]);

    $config = $this->resolver->resolve($agent);

    expect($config['instructions'])->toBe('Default instructions.')
        ->and($config['model'])->toBe('claude-opus-4-6')
        ->and($config['provider'])->toBe('anthropic')
        ->and($config['temperature'])->toBe(0.6)
        ->and($config['max_steps'])->toBe(8)
        ->and($config['max_tokens'])->toBe(2048)
        ->and($config['timeout'])->toBe(90);
});

test('resolver filters tools by agent provider', function () {
    $organization = Organization::factory()->create();
    $agentRole = AgentRole::factory()
        ->forOrganization($organization)
        ->create([
            'tools' => ['web_search', 'web_fetch', 'file_search'],
        ]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
        'provider' => 'openai',
    ]);

    $config = $this->resolver->resolve($agent);

    expect($config['tools'])->toContain('web_search')
        ->and($config['tools'])->not->toContain('web_fetch')
        ->and($config['tools'])->toContain('file_search');
});

test('resolver returns null instructions when type has none', function () {
    $organization = Organization::factory()->create();
    $agentRole = AgentRole::factory()
        ->forOrganization($organization)
        ->create([
            'instructions' => null,
            'tools' => [],
        ]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
    ]);

    $config = $this->resolver->resolve($agent);

    expect($config['instructions'])->toBeNull()
        ->and($config['tools'])->toBe([]);
});

test('resolver merges agent role instructions with assigned skill content', function () {
    $organization = Organization::factory()->create();
    $agentRole = AgentRole::factory()
        ->forOrganization($organization)
        ->create([
            'instructions' => 'You are a coding agent.',
            'tools' => [],
        ]);
    $skillLaravel = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Laravel',
        'content' => 'Use Laravel conventions and Eloquent.',
    ]);
    $skillFlux = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Flux UI',
        'content' => 'Use Flux UI components for forms.',
    ]);
    $agentRole->skills()->attach($skillLaravel->id, ['position' => 0]);
    $agentRole->skills()->attach($skillFlux->id, ['position' => 1]);

    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
    ]);

    $config = $this->resolver->resolve($agent);

    expect($config['instructions'])->toContain('You are a coding agent.')
        ->and($config['instructions'])->toContain('## Skill: Laravel')
        ->and($config['instructions'])->toContain('Use Laravel conventions and Eloquent.')
        ->and($config['instructions'])->toContain('## Skill: Flux UI')
        ->and($config['instructions'])->toContain('Use Flux UI components for forms.');
});

test('resolver includes project instructions when work item is a bug', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'instructions' => 'Always write tests first.',
    ]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $agentRole = AgentRole::factory()
        ->forOrganization($organization)
        ->create([
            'instructions' => 'You fix bugs.',
            'tools' => [],
        ]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
    ]);

    $config = $this->resolver->resolve($agent, $bug);

    expect($config['instructions'])->toContain('## Project: '.$project->name)
        ->and($config['instructions'])->toContain('Always write tests first.')
        ->and($config['instructions'])->toContain('You fix bugs.');
});

test('resolver includes project instructions when work item is a story', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'instructions' => 'Use PHP 8.5 features.',
    ]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $agentRole = AgentRole::factory()
        ->forOrganization($organization)
        ->create([
            'instructions' => 'You build features.',
            'tools' => [],
        ]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
    ]);

    $config = $this->resolver->resolve($agent, $story);

    expect($config['instructions'])->toContain('Use PHP 8.5 features.')
        ->and($config['instructions'])->toContain('You build features.');
});

test('resolver omits project instructions when project has none', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'instructions' => null,
    ]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $agentRole = AgentRole::factory()
        ->forOrganization($organization)
        ->create([
            'instructions' => 'You fix bugs.',
            'tools' => [],
        ]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
    ]);

    $config = $this->resolver->resolve($agent, $bug);

    expect($config['instructions'])->toBe('You fix bugs.')
        ->and($config['instructions'])->not->toContain('## Project:');
});

test('resolver skips skills with empty or null content', function () {
    $organization = Organization::factory()->create();
    $agentRole = AgentRole::factory()
        ->forOrganization($organization)
        ->create([
            'instructions' => 'Base instructions.',
            'tools' => [],
        ]);
    $skillWithContent = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'With Content',
        'content' => 'Has content.',
    ]);
    $skillEmpty = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Empty',
        'content' => '',
    ]);
    $skillNull = Skill::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Null',
        'content' => null,
    ]);
    $agentRole->skills()->attach($skillWithContent->id, ['position' => 0]);
    $agentRole->skills()->attach($skillEmpty->id, ['position' => 1]);
    $agentRole->skills()->attach($skillNull->id, ['position' => 2]);

    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
    ]);

    $config = $this->resolver->resolve($agent);

    expect($config['instructions'])->toContain('## Skill: With Content')
        ->and($config['instructions'])->toContain('Has content.')
        ->and($config['instructions'])->not->toContain('## Skill: Empty')
        ->and($config['instructions'])->not->toContain('## Skill: Null');
});
