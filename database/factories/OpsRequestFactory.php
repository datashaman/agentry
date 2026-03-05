<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OpsRequest>
 */
class OpsRequestFactory extends Factory
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
            'category' => fake()->randomElement(['deployment', 'infrastructure', 'config', 'data']),
            'execution_type' => fake()->randomElement(['automated', 'supervised', 'manual']),
            'risk_level' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'environment' => fake()->optional()->randomElement(['production', 'staging', 'development']),
            'scheduled_at' => fake()->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }
}
