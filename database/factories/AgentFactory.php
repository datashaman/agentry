<?php

namespace Database\Factories;

use App\Models\AgentRole;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent>
 */
class AgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_role_id' => AgentRole::factory(),
            'team_id' => Team::factory(),
            'name' => fake()->unique()->words(2, true),
            'model' => fake()->randomElement(['claude-sonnet-4-6', 'claude-opus-4-6', 'claude-haiku-4-5']),
            'provider' => fake()->randomElement(['anthropic', 'openai', 'google']),
            'confidence_threshold' => fake()->randomFloat(2, 0.5, 1.0),
            'status' => 'idle',
        ];
    }

    /**
     * Configure the agent with a schedule.
     */
    public function scheduled(string $schedule = 'every_5_minutes', string $instructions = 'Perform scheduled check.'): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule' => $schedule,
            'scheduled_instructions' => $instructions,
        ]);
    }
}
