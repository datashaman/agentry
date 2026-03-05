<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Critique>
 */
class CritiqueFactory extends Factory
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
            'agent_id' => Agent::factory(),
            'critic_type' => fake()->randomElement(['spec', 'code', 'test', 'design']),
            'revision' => fake()->numberBetween(1, 5),
            'issues' => [fake()->sentence()],
            'questions' => [fake()->sentence()],
            'recommendations' => [fake()->sentence()],
            'severity' => fake()->randomElement(['blocking', 'major', 'minor', 'suggestion']),
            'disposition' => fake()->randomElement(['pending', 'accepted', 'rejected', 'deferred']),
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
}
