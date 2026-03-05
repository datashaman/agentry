<?php

use App\Agents\ToolRegistry;
use App\Models\AgentType;
use App\Models\Organization;

beforeEach(function () {
    $this->registry = new ToolRegistry;
});

test('resolveTools returns provider tools when provider is supported', function () {
    $organization = Organization::factory()->create();
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create(['tools' => ['web_search', 'web_fetch']]);

    $resolved = $this->registry->resolveTools($agentType, 'anthropic');

    expect($resolved)->toBe(['web_search', 'web_fetch']);
});

test('resolveTools filters provider tools by provider', function () {
    $organization = Organization::factory()->create();
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create(['tools' => ['web_search', 'web_fetch', 'file_search']]);

    $resolved = $this->registry->resolveTools($agentType, 'openai');

    expect($resolved)->toContain('web_search')
        ->and($resolved)->not->toContain('web_fetch')
        ->and($resolved)->toContain('file_search');
});

test('resolveTools filters out provider tools when provider unsupported', function () {
    $organization = Organization::factory()->create();
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create(['tools' => ['web_search', 'web_fetch']]);

    $resolved = $this->registry->resolveTools($agentType, 'gemini');

    expect($resolved)->toBe(['web_search', 'web_fetch']);
});

test('resolveTools includes custom tools regardless of provider', function () {
    $this->registry->registerCustomTool('my_tool', \stdClass::class);
    $organization = Organization::factory()->create();
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create(['tools' => ['my_tool', 'web_search']]);

    $resolved = $this->registry->resolveTools($agentType, 'anthropic');

    expect($resolved)->toContain('my_tool')
        ->and($resolved)->toContain('web_search');
});

test('resolveTools includes custom tool even when provider tool filtered', function () {
    $this->registry->registerCustomTool('code_editor', \stdClass::class);
    $organization = Organization::factory()->create();
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create(['tools' => ['code_editor', 'web_fetch']]);

    $resolved = $this->registry->resolveTools($agentType, 'openai');

    expect($resolved)->toContain('code_editor')
        ->and($resolved)->not->toContain('web_fetch');
});

test('resolveTools skips unknown tool ids', function () {
    $organization = Organization::factory()->create();
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create(['tools' => ['unknown_tool', 'web_search']]);

    $resolved = $this->registry->resolveTools($agentType, 'anthropic');

    expect($resolved)->not->toContain('unknown_tool')
        ->and($resolved)->toContain('web_search');
});

test('resolveTools returns empty array when agent type has no tools', function () {
    $organization = Organization::factory()->create();
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create(['tools' => []]);

    $resolved = $this->registry->resolveTools($agentType, 'anthropic');

    expect($resolved)->toBe([]);
});

test('resolveTools handles null tools as empty', function () {
    $organization = Organization::factory()->create();
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create();
    $agentType->tools = null;

    $resolved = $this->registry->resolveTools($agentType, 'anthropic');

    expect($resolved)->toBe([]);
});

test('resolveTools is case insensitive for provider', function () {
    $organization = Organization::factory()->create();
    $agentType = AgentType::factory()
        ->forOrganization($organization)
        ->create(['tools' => ['web_search']]);

    $resolved = $this->registry->resolveTools($agentType, 'ANTHROPIC');

    expect($resolved)->toContain('web_search');
});

test('getProviderTools returns provider tool metadata', function () {
    $tools = ToolRegistry::getProviderTools();

    expect($tools)->toHaveKey('web_search')
        ->and($tools['web_search'])->toBe(['anthropic', 'openai', 'gemini'])
        ->and($tools)->toHaveKey('web_fetch')
        ->and($tools['web_fetch'])->toBe(['anthropic', 'gemini'])
        ->and($tools)->toHaveKey('file_search')
        ->and($tools['file_search'])->toBe(['openai', 'gemini']);
});

test('isProviderTool returns true for known provider tools', function () {
    expect(ToolRegistry::isProviderTool('web_search'))->toBeTrue()
        ->and(ToolRegistry::isProviderTool('web_fetch'))->toBeTrue()
        ->and(ToolRegistry::isProviderTool('file_search'))->toBeTrue();
});

test('isProviderTool returns false for unknown tools', function () {
    expect(ToolRegistry::isProviderTool('unknown'))->toBeFalse();
});

test('isCustomTool returns true after registration', function () {
    $this->registry->registerCustomTool('my_tool', \stdClass::class);

    expect($this->registry->isCustomTool('my_tool'))->toBeTrue();
});

test('isCustomTool returns false for unregistered tool', function () {
    expect($this->registry->isCustomTool('web_search'))->toBeFalse();
});
