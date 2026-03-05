<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bug>
 */
class BugFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => 'new',
            'severity' => fake()->randomElement(['critical', 'major', 'minor', 'trivial']),
            'priority' => fake()->numberBetween(0, 10),
            'environment' => fake()->optional()->randomElement(['production', 'staging', 'development']),
            'repro_steps' => fake()->optional()->paragraph(),
        ];
    }
}
