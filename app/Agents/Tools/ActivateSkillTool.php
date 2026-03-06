<?php

namespace App\Agents\Tools;

use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Skill;

class ActivateSkillTool
{
    /** @var list<int> */
    protected array $activatedSkillIds = [];

    /**
     * Tool definition for SDK registration.
     *
     * @return array{name: string, description: string, parameters: array}
     */
    public static function definition(): array
    {
        return [
            'name' => 'activate_skill',
            'description' => 'Load the full content of a skill by ID. Use this when a task matches a skill from the available_skills catalog.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'skill_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the skill to activate.',
                    ],
                ],
                'required' => ['skill_id'],
            ],
        ];
    }

    /**
     * Execute the tool: return skill content for the given ID.
     */
    public function execute(int $skillId, Organization $org, AgentRole $role): string
    {
        $skill = Skill::find($skillId);

        if (! $skill || $skill->organization_id !== $org->id) {
            return '<error>Skill not found or not accessible.</error>';
        }

        if (! $role->skills()->where('skills.id', $skillId)->exists()) {
            return '<error>Skill is not assigned to your role.</error>';
        }

        if (in_array($skillId, $this->activatedSkillIds, true)) {
            return '<skill_content name="'.$skill->slug.'" already_activated="true">Skill already loaded in this session.</skill_content>';
        }

        $this->activatedSkillIds[] = $skillId;

        $content = trim($skill->content ?? '');

        $resourceInfo = '';
        if (! empty($skill->resource_paths)) {
            $resourceInfo = "\n\n<available_resources>\n";
            foreach ($skill->resource_paths as $path) {
                $resourceInfo .= "  <resource>{$path}</resource>\n";
            }
            $resourceInfo .= '</available_resources>';
        }

        return "<skill_content name=\"{$skill->slug}\" id=\"{$skill->id}\">\n{$content}{$resourceInfo}\n</skill_content>";
    }

    public function resetActivations(): void
    {
        $this->activatedSkillIds = [];
    }
}
