<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\PullRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pull_request_id' => PullRequest::factory(),
            'agent_id' => Agent::factory(),
            'status' => fake()->randomElement(['pending', 'approved', 'changes_requested', 'commented']),
            'body' => fake()->paragraph(),
            'submitted_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
