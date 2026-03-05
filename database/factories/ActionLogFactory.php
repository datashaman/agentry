<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActionLog>
 */
class ActionLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $story = Story::factory()->create();

        return [
            'agent_id' => Agent::factory(),
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'action' => fake()->randomElement(['created_branch', 'committed_code', 'opened_pr', 'ran_tests', 'deployed', 'escalated']),
            'reasoning' => fake()->sentence(),
            'timestamp' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function forStory(): static
    {
        return $this->state(function () {
            $story = Story::factory()->create();

            return [
                'work_item_id' => $story->id,
                'work_item_type' => Story::class,
            ];
        });
    }
}
