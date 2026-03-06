<?php

namespace App\Agents\Tools;

use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Skill;
use App\Services\GitHubAppService;

class LoadSkillResourceTool
{
    public function __construct(
        protected GitHubAppService $github
    ) {}

    /**
     * Tool definition for SDK registration.
     *
     * @return array{name: string, description: string, parameters: array}
     */
    public static function definition(): array
    {
        return [
            'name' => 'load_skill_resource',
            'description' => 'Load a resource file associated with an imported skill (scripts, references, assets).',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'skill_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the skill.',
                    ],
                    'resource_path' => [
                        'type' => 'string',
                        'description' => 'The relative path of the resource to load.',
                    ],
                ],
                'required' => ['skill_id', 'resource_path'],
            ],
        ];
    }

    /**
     * Execute the tool: fetch a resource file from the skill's source repo.
     */
    public function execute(int $skillId, string $resourcePath, Organization $org, AgentRole $role): string
    {
        $skill = Skill::find($skillId);

        if (! $skill || $skill->organization_id !== $org->id) {
            return '<error>Skill not found or not accessible.</error>';
        }

        if (! $role->skills()->where('skills.id', $skillId)->exists()) {
            return '<error>Skill is not assigned to your role.</error>';
        }

        if (! $skill->isImported() || empty($skill->resource_paths)) {
            return '<error>Skill has no loadable resources.</error>';
        }

        if (! in_array($resourcePath, $skill->resource_paths, true)) {
            return '<error>Resource path not found in skill resources.</error>';
        }

        $repo = $skill->sourceRepo;

        if (! $repo) {
            return '<error>Source repo no longer available.</error>';
        }

        $content = $this->github->getFileContent($repo, $resourcePath);

        if ($content === null) {
            return '<error>Could not fetch resource content from GitHub.</error>';
        }

        return "<skill_resource skill=\"{$skill->slug}\" path=\"{$resourcePath}\">\n{$content}\n</skill_resource>";
    }
}
