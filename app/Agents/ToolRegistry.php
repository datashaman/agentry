<?php

namespace App\Agents;

use App\Models\AgentType;

class ToolRegistry
{
    /**
     * Provider tools: ID => supported providers.
     * Aligned with Claude Code capabilities: bash, text_editor, code_execution, web_search, web_fetch, file_search.
     * - bash, text_editor: Anthropic-native (Claude Code style)
     * - web_search, web_fetch, file_search: Laravel AI SDK provider tools
     *
     * @var array<string, list<string>>
     */
    protected static array $providerTools = [
        'bash' => ['anthropic'],
        'text_editor' => ['anthropic'],
        'code_execution' => ['anthropic'],
        'web_search' => ['anthropic', 'openai', 'gemini'],
        'web_fetch' => ['anthropic', 'gemini'],
        'file_search' => ['openai', 'gemini'],
    ];

    /**
     * Custom tools: ID => class name (provider-agnostic).
     *
     * @var array<string, class-string>
     */
    protected array $customTools = [];

    /**
     * Resolve tools for an agent type filtered by provider.
     * Returns tool IDs valid for the given provider:
     * - Custom tools: always included
     * - Provider tools: included only if provider is in supported list
     * - Unknown tool IDs: skipped
     *
     * @return list<string>
     */
    public function resolveTools(AgentType $agentType, string $provider): array
    {
        $toolIds = $agentType->tools ?? [];
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
                $supported = array_map('strtolower', static::$providerTools[$id]);
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
     * Get provider tool metadata (id => supported providers).
     *
     * @return array<string, list<string>>
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
