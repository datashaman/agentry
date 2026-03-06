<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Repo;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SkillImportService
{
    public function __construct(
        protected GitHubAppService $github,
        protected SkillMdParser $parser
    ) {}

    /**
     * Discover SKILL.md files in a repo's .agents/skills/ directory.
     *
     * @return Collection<int, array{path: string, parsed: array, sha: string, resource_paths: list<string>}>
     */
    public function discoverSkillsInRepo(Repo $repo): Collection
    {
        $tree = $this->github->getTree($repo);

        if (! $tree) {
            return collect();
        }

        $skillFiles = collect($tree)->filter(function (array $item) {
            return $item['type'] === 'blob'
                && preg_match('#^\.agents/skills/[^/]+/SKILL\.md$#', $item['path']);
        });

        $discovered = collect();

        foreach ($skillFiles as $item) {
            $content = $this->github->getFileContent($repo, $item['path']);

            if ($content === null) {
                continue;
            }

            $parsed = $this->parser->parse($content);

            $skillDir = dirname($item['path']);
            $resourcePaths = collect($tree)
                ->filter(function (array $entry) use ($skillDir, $item) {
                    return $entry['type'] === 'blob'
                        && str_starts_with($entry['path'], $skillDir.'/')
                        && $entry['path'] !== $item['path'];
                })
                ->pluck('path')
                ->values()
                ->all();

            $discovered->push([
                'path' => $item['path'],
                'parsed' => $parsed,
                'sha' => $item['sha'],
                'resource_paths' => $resourcePaths,
            ]);
        }

        return $discovered;
    }

    /**
     * Import a single discovered skill into an organization.
     */
    public function importSkill(Organization $org, array $discovered, Repo $repo): Skill
    {
        $parsed = $discovered['parsed'];
        $slug = $parsed['name'] ?? Str::slug(basename(dirname($discovered['path'])));

        $skill = Skill::query()
            ->where('organization_id', $org->id)
            ->where('slug', $slug)
            ->first();

        $attributes = [
            'organization_id' => $org->id,
            'name' => Str::headline($slug),
            'slug' => $slug,
            'description' => $parsed['description'] ?? '',
            'content' => $parsed['body'],
            'source_repo_id' => $repo->id,
            'source_path' => $discovered['path'],
            'source_sha' => $discovered['sha'],
            'frontmatter_metadata' => array_filter([
                'license' => $parsed['license'],
                'compatibility' => $parsed['compatibility'],
                'metadata' => $parsed['metadata'],
                'allowed-tools' => $parsed['allowed_tools'],
            ]),
            'resource_paths' => $discovered['resource_paths'] ?: null,
        ];

        if ($skill) {
            $skill->update($attributes);
        } else {
            $skill = Skill::create($attributes);
        }

        return $skill;
    }

    /**
     * Sync all skills from a repo into an organization.
     *
     * @return array{imported: int, updated: int, errors: int, skills: Collection<int, Skill>}
     */
    public function syncSkillsFromRepo(Organization $org, Repo $repo): array
    {
        $discovered = $this->discoverSkillsInRepo($repo);
        $imported = 0;
        $updated = 0;
        $errors = 0;
        $skills = collect();

        foreach ($discovered as $item) {
            if (! $item['parsed']['valid']) {
                $errors++;

                continue;
            }

            $slug = $item['parsed']['name'];
            $exists = Skill::query()
                ->where('organization_id', $org->id)
                ->where('slug', $slug)
                ->exists();

            $skill = $this->importSkill($org, $item, $repo);
            $skills->push($skill);

            if ($exists) {
                $updated++;
            } else {
                $imported++;
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'skills' => $skills,
        ];
    }
}
