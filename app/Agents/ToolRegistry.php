<?php

namespace App\Agents;

use App\Agents\Tools\ActivateSkillTool;
use App\Agents\Tools\LoadSkillResourceTool;
use App\Models\AgentRole;

class ToolRegistry
{
    /**
     * Provider tools: ID => [providers, description].
     * Aligned with Claude Code capabilities: bash, text_editor, code_execution, web_search, web_fetch, file_search.
     * - bash, text_editor: Anthropic-native (Claude Code style)
     * - web_search, web_fetch, file_search: Laravel AI SDK provider tools
     *
     * @var array<string, array{providers: list<string>, description: string}>
     */
    protected static array $providerTools = [
        'bash' => [
            'providers' => ['anthropic'],
            'description' => 'Execute shell commands in a sandboxed environment.',
        ],
        'text_editor' => [
            'providers' => ['anthropic'],
            'description' => 'Read, create, and edit files.',
        ],
        'code_execution' => [
            'providers' => ['anthropic'],
            'description' => 'Run code snippets and return output.',
        ],
        'web_search' => [
            'providers' => ['anthropic', 'openai', 'gemini'],
            'description' => 'Search the web for information.',
        ],
        'web_fetch' => [
            'providers' => ['anthropic', 'gemini'],
            'description' => 'Fetch content from a URL.',
        ],
        'file_search' => [
            'providers' => ['openai', 'gemini'],
            'description' => 'Search uploaded files and documents.',
        ],
    ];

    /**
     * Custom tools: ID => class name (provider-agnostic).
     *
     * @var array<string, class-string>
     */
    protected array $customTools = [
        'activate_skill' => ActivateSkillTool::class,
        'load_skill_resource' => LoadSkillResourceTool::class,
    ];

    /**
     * Resolve tools for an agent role filtered by provider.
     * Returns tool IDs valid for the given provider:
     * - Custom tools: always included
     * - Provider tools: included only if provider is in supported list
     * - Unknown tool IDs: skipped
     *
     * @return list<string>
     */
    public function resolveTools(AgentRole $agentRole, string $provider): array
    {
        $toolIds = $agentRole->tools ?? [];
        $provider = strtolower($provider);
        $resolved = [];

        foreach ($toolIds as $id) {
            $id = is_string($id) ? trim($id) : (string) $id;
            if ($id === '') {
                continue;
            }

            if (isset($this->customTools[$id])) {
                $resolved[] = $id;

                continue;
            }

            if (isset(static::$providerTools[$id])) {
                $supported = array_map('strtolower', static::$providerTools[$id]['providers']);
                if (in_array($provider, $supported, true)) {
                    $resolved[] = $id;
                }
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * Register a custom tool by ID and class name.
     *
     * @param  class-string  $class
     */
    public function registerCustomTool(string $id, string $class): void
    {
        $this->customTools[$id] = $class;
    }

    /**
     * Get provider tool metadata.
     *
     * @return array<string, array{providers: list<string>, description: string}>
     */
    public static function getProviderTools(): array
    {
        return static::$providerTools;
    }

    /**
     * Check if a tool ID is a known provider tool.
     */
    public static function isProviderTool(string $id): bool
    {
        return isset(static::$providerTools[$id]);
    }

    /**
     * Check if a tool ID is registered as custom.
     */
    public function isCustomTool(string $id): bool
    {
        return isset($this->customTools[$id]);
    }
}
