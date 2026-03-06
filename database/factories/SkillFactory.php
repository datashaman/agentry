<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Repo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Skill>
 */
class SkillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => str_replace(' ', '-', strtolower($name)),
            'description' => fake()->sentence(),
            'content' => fake()->paragraphs(2, true),
        ];
    }

    /**
     * Create a skill for a specific organization.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->id,
        ]);
    }

    /**
     * Create a skill imported from a repo.
     */
    public function imported(Repo $repo): static
    {
        return $this->state(fn (array $attributes) => [
            'source_repo_id' => $repo->id,
            'source_path' => '.agents/skills/'.($attributes['slug'] ?? 'example').'/SKILL.md',
            'source_sha' => fake()->sha1(),
            'frontmatter_metadata' => ['license' => 'MIT'],
        ]);
    }
}
