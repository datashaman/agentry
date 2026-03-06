<?php

namespace App\Agents;

use App\Models\Agent;
use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Project;
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

        if ($this->shouldIncludeSkillTools($type)) {
            $tools[] = 'activate_skill';

            if ($this->hasSkillResources($type)) {
                $tools[] = 'load_skill_resource';
            }
        }

        $project = $this->resolveProject($workItem);
        $instructions = $this->buildInstructions($type, $project);

        return [
            'instructions' => $instructions,
            'tools' => array_values(array_unique($tools)),
            'model' => $agent->model ?? $type?->default_model ?? '',
            'provider' => $agent->provider ?? $type?->default_provider ?? 'anthropic',
            'temperature' => $agent->temperature ?? $type?->default_temperature,
            'max_steps' => $agent->max_steps ?? $type?->default_max_steps,
            'max_tokens' => $agent->max_tokens ?? $type?->default_max_tokens,
            'timeout' => $agent->timeout ?? $type?->default_timeout,
        ];
    }

    protected function resolveProject(Story|Bug|OpsRequest|null $workItem): ?Project
    {
        if ($workItem === null) {
            return null;
        }

        if ($workItem instanceof Story) {
            return $workItem->epic?->project;
        }

        return $workItem->project;
    }

    protected function buildInstructions(?\App\Models\AgentRole $type, ?Project $project = null): ?string
    {
        $parts = [];

        if ($project && ! empty(trim((string) $project->instructions ?? ''))) {
            $parts[] = "## Project: {$project->name}\n".trim($project->instructions);
        }

        if ($type !== null) {
            if (! empty(trim((string) $type->instructions ?? ''))) {
                $parts[] = trim($type->instructions);
            }

            $catalog = $this->buildSkillCatalog($type);
            if ($catalog !== null) {
                $parts[] = $catalog;
            }
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    protected function buildSkillCatalog(?\App\Models\AgentRole $type): ?string
    {
        if ($type === null || ($type->skills ?? collect())->isEmpty()) {
            return null;
        }

        $entries = [];
        foreach ($type->skills as $skill) {
            $desc = trim($skill->description ?? '');
            $entries[] = "  <skill><name>{$skill->slug}</name><description>{$desc}</description><id>{$skill->id}</id></skill>";
        }

        $catalog = "<available_skills>\n".implode("\n", $entries)."\n</available_skills>";

        $instructions = "You have domain-specific skills available. When a task matches a skill's description, call the `activate_skill` tool with the skill's ID to load its full instructions before proceeding.";

        return $instructions."\n\n".$catalog;
    }

    protected function shouldIncludeSkillTools(?\App\Models\AgentRole $type): bool
    {
        return $type !== null && ($type->skills ?? collect())->isNotEmpty();
    }

    protected function hasSkillResources(?\App\Models\AgentRole $type): bool
    {
        if ($type === null) {
            return false;
        }

        foreach ($type->skills ?? [] as $skill) {
            if (! empty($skill->resource_paths)) {
                return true;
            }
        }

        return false;
    }
}
