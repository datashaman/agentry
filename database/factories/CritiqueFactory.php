<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\OpsRequest;
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
        $opsRequest = OpsRequest::factory()->create();

        return [
            'work_item_id' => $opsRequest->id,
            'work_item_type' => OpsRequest::class,
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

    public function forOpsRequest(?OpsRequest $opsRequest = null): static
    {
        $opsRequest ??= OpsRequest::factory()->create();

        return $this->state(fn () => [
            'work_item_id' => $opsRequest->id,
            'work_item_type' => OpsRequest::class,
        ]);
    }
}
