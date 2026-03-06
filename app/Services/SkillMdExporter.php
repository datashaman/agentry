<?php

namespace App\Services;

use App\Models\Skill;
use Symfony\Component\Yaml\Yaml;

class SkillMdExporter
{
    /**
     * Export a Skill as a spec-compliant SKILL.md string.
     */
    public function export(Skill $skill): string
    {
        $frontmatter = [
            'name' => $skill->slug,
            'description' => $skill->description ?? '',
        ];

        $metadata = $skill->frontmatter_metadata ?? [];

        if (! empty($metadata['license'])) {
            $frontmatter['license'] = $metadata['license'];
        }

        if (! empty($metadata['compatibility'])) {
            $frontmatter['compatibility'] = $metadata['compatibility'];
        }

        if (! empty($metadata['metadata'])) {
            $frontmatter['metadata'] = $metadata['metadata'];
        }

        if (! empty($metadata['allowed-tools'])) {
            $frontmatter['allowed-tools'] = $metadata['allowed-tools'];
        }

        $yaml = Yaml::dump($frontmatter, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $parts = ["---\n{$yaml}---"];

        $content = trim($skill->content ?? '');
        if ($content !== '') {
            $parts[] = $content;
        }

        return implode("\n\n", $parts)."\n";
    }
}
