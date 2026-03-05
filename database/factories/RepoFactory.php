<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Repo>
 */
class RepoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'project_id' => Project::factory(),
            'name' => $name,
            'url' => 'https://github.com/example/'.$name.'.git',
            'primary_language' => fake()->optional()->randomElement(['PHP', 'JavaScript', 'TypeScript', 'Python', 'Go', 'Rust']),
            'default_branch' => 'main',
            'tags' => fake()->optional()->randomElements(['backend', 'frontend', 'api', 'library', 'service', 'infrastructure'], fake()->numberBetween(1, 3)),
        ];
    }
}
