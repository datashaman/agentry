<?php

namespace App\Agents;

use App\Models\Agent;
use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Story;

class AgentResolver
{
    public function __construct(
        protected ToolRegistry $toolRegistry
    ) {}

    /**
     * Resolve Agent + AgentType into SDK-ready config.
     * When workItem is provided and context-aware is enabled, merges skills
     * whose context_triggers match the work item's project/repo context.
     *
     * @param  Story|Bug|OpsRequest|null  $workItem
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
        $agent->loadMissing(['agentType', 'agentType.skills', 'agentType.organization']);
        $type = $agent->agentType;

        $tools = $type
            ? $this->toolRegistry->resolveTools($type, $agent->provider)
            : [];

        $contextSkills = $this->getContextMatchedSkills($type, $workItem);
        $instructions = $this->buildInstructions($type, $contextSkills);

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

    /**
     * @param  Story|Bug|OpsRequest|null  $workItem
     * @return \Illuminate\Support\Collection<int, Skill>
     */
    protected function getContextMatchedSkills(?\App\Models\AgentType $type, Story|Bug|OpsRequest|null $workItem): \Illuminate\Support\Collection
    {
        if ($type === null || $workItem === null || ! config('skills.context_aware_enabled', true)) {
            return collect();
        }

        $organization = $type->organization;
        if ($organization === null) {
            return collect();
        }

        $project = $this->getProjectFromWorkItem($workItem);
        if ($project === null) {
            return collect();
        }

        $repos = $project->repos()->get();
        $assignedSkillIds = $type->skills->pluck('id')->toArray();

        return Skill::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('context_triggers')
            ->get()
            ->filter(function (Skill $skill) use ($assignedSkillIds, $repos) {
                $triggers = $skill->context_triggers ?? [];
                if (! is_array($triggers) || empty($triggers)) {
                    return false;
                }

                return ! in_array($skill->id, $assignedSkillIds) && $this->triggersMatch($triggers, $repos);
            });
    }

    /**
     * @param  array<string, mixed>  $triggers
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\Repo>  $repos
     */
    protected function triggersMatch(array $triggers, \Illuminate\Database\Eloquent\Collection $repos): bool
    {
        if (empty($triggers) || $repos->isEmpty()) {
            return false;
        }

        foreach ($repos as $repo) {
            $matches = true;

            if (isset($triggers['repo.primary_language'])) {
                $allowed = (array) $triggers['repo.primary_language'];
                $lang = $repo->primary_language ? strtolower($repo->primary_language) : '';
                if (! in_array($lang, array_map('strtolower', $allowed))) {
                    $matches = false;
                }
            }

            if (isset($triggers['repo.tags']) && $matches) {
                $allowedTags = array_map('strtolower', (array) $triggers['repo.tags']);
                $repoTags = array_map('strtolower', $repo->tags ?? []);
                if (empty(array_intersect($allowedTags, $repoTags))) {
                    $matches = false;
                }
            }

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    protected function getProjectFromWorkItem(Story|Bug|OpsRequest $workItem): ?Project
    {
        return match (true) {
            $workItem instanceof Story => $workItem->epic?->project,
            $workItem instanceof Bug, $workItem instanceof OpsRequest => $workItem->project,
            default => null,
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Skill>  $contextSkills
     */
    protected function buildInstructions(?\App\Models\AgentType $type, \Illuminate\Support\Collection $contextSkills = new \Illuminate\Support\Collection): ?string
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

        foreach ($contextSkills as $skill) {
            $content = trim((string) ($skill->content ?? ''));
            if ($content !== '') {
                $parts[] = "## Skill: {$skill->name}\n{$content}";
            }
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }
}
