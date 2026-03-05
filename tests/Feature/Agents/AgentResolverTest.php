<?php

use App\Agents\AgentResolver;
use App\Agents\ToolRegistry;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Organization;
use App\Models\Team;

beforeEach(function () {
    $this->resolver = new AgentResolver(new ToolRegistry);
});

test('resolver returns merged config with agent overrides taking precedence', function () {
    $organization = Organization::factory()->create();
    $agentType = AgentType::factory()
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
        'agent_type_id' => $agentType->id,
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
    $agentType = AgentType::factory()
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
        'agent_type_id' => $agentType->id,
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
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create([
            'tools' => ['web_search', 'web_fetch', 'file_search'],
        ]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_type_id' => $agentType->id,
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
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create([
            'instructions' => null,
            'tools' => [],
        ]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'agent_type_id' => $agentType->id,
        'team_id' => $team->id,
    ]);

    $config = $this->resolver->resolve($agent);

    expect($config['instructions'])->toBeNull()
        ->and($config['tools'])->toBe([]);
});
