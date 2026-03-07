<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\OpsRequest;
use App\Models\WorkItem;
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
        $opsRequest = OpsRequest::factory()->create();

        return [
            'work_item_id' => $opsRequest->id,
            'work_item_type' => OpsRequest::class,
            'raised_by_agent_id' => Agent::factory(),
            'trigger_type' => fake()->randomElement(['confidence', 'risk', 'policy', 'ambiguity']),
            'trigger_class' => fake()->optional()->word(),
            'agent_confidence' => fake()->randomFloat(2, 0, 1),
            'reason' => fake()->sentence(),
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

    public function forWorkItem(?WorkItem $workItem = null): static
    {
        $workItem ??= WorkItem::factory()->create();

        return $this->state(fn () => [
            'work_item_id' => $workItem->id,
            'work_item_type' => WorkItem::class,
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
