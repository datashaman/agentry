<?php

namespace App\Agents;

use App\Models\Agent;

class AgentResolver
{
    public function __construct(
        protected ToolRegistry $toolRegistry
    ) {}

    /**
     * Resolve Agent + AgentType into SDK-ready config.
     * Merges type defaults with agent overrides; filters tools by agent provider.
     *
     * @return array{
     *     instructions: ?string,
     *     tools: list<string>,
     *     model: string,
     *     provider: string,
     *     temperature: ?float,
     *     max_steps: ?int,
     *     max_tokens: ?int,
     *     timeout: ?int,
     * }
     */
    public function resolve(Agent $agent): array
    {
        $agent->loadMissing('agentType');
        $type = $agent->agentType;

        $tools = $type
            ? $this->toolRegistry->resolveTools($type, $agent->provider)
            : [];

        return [
            'instructions' => $type?->instructions,
            'tools' => $tools,
            'model' => $agent->model ?? $type?->default_model ?? '',
            'provider' => $agent->provider ?? $type?->default_provider ?? 'anthropic',
            'temperature' => $agent->temperature ?? $type?->default_temperature,
            'max_steps' => $agent->max_steps ?? $type?->default_max_steps,
            'max_tokens' => $agent->max_tokens ?? $type?->default_max_tokens,
            'timeout' => $agent->timeout ?? $type?->default_timeout,
        ];
    }
}
