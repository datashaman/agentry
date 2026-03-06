<?php

namespace App\Agents;

use App\Models\Agent;
use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Story;

class AgentResolver
{
    public function __construct(
        protected ToolRegistry $toolRegistry
    ) {}

    /**
     * Resolve Agent + AgentRole into SDK-ready config.
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
    public function resolve(Agent $agent, Story|Bug|OpsRequest|null $workItem = null): array
    {
        $agent->loadMissing(['agentRole', 'agentRole.skills', 'agentRole.organization']);
        $type = $agent->agentRole;

        $tools = $type
            ? $this->toolRegistry->resolveTools($type, $agent->provider)
            : [];

        $instructions = $this->buildInstructions($type);

        return [
            'instructions' => $instructions,
            'tools' => $tools,
            'model' => $agent->model ?? $type?->default_model ?? '',
            'provider' => $agent->provider ?? $type?->default_provider ?? 'anthropic',
            'temperature' => $agent->temperature ?? $type?->default_temperature,
            'max_steps' => $agent->max_steps ?? $type?->default_max_steps,
            'max_tokens' => $agent->max_tokens ?? $type?->default_max_tokens,
            'timeout' => $agent->timeout ?? $type?->default_timeout,
        ];
    }

    protected function buildInstructions(?\App\Models\AgentRole $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $parts = [];

        if (! empty(trim((string) $type->instructions ?? ''))) {
            $parts[] = trim($type->instructions);
        }

        foreach ($type->skills ?? [] as $skill) {
            $content = trim((string) ($skill->content ?? ''));
            if ($content !== '') {
                $parts[] = "## Skill: {$skill->name}\n{$content}";
            }
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }
}
