<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HitlEscalation>
 */
class HitlEscalationFactory extends Factory
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
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'raised_by_agent_id' => Agent::factory(),
            'trigger_type' => fake()->randomElement(['confidence', 'risk', 'policy', 'ambiguity']),
            'trigger_class' => fake()->optional()->word(),
            'agent_confidence' => fake()->randomFloat(2, 0, 1),
            'reason' => fake()->sentence(),
        ];
    }

    public function forStory(?Story $story = null): static
    {
        $story ??= Story::factory()->create();

        return $this->state(fn () => [
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'resolution' => fake()->sentence(),
            'resolved_by' => fake()->name(),
            'resolved_at' => now(),
        ]);
    }
}
