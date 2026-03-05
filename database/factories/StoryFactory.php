<?php

namespace Database\Factories;

use App\Models\Epic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Story>
 */
class StoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'epic_id' => Epic::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => 'backlog',
            'priority' => fake()->numberBetween(0, 10),
            'story_points' => fake()->randomElement([1, 2, 3, 5, 8, 13]),
            'due_date' => fake()->optional()->dateTimeBetween('+1 week', '+3 months'),
            'spec_revision_count' => 0,
            'substantial_change' => false,
            'dev_iteration_count' => 0,
        ];
    }
}
