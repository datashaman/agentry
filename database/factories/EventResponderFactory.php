<?php

namespace Database\Factories;

use App\Models\AgentRole;
use App\Models\EventResponder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventResponder>
 */
class EventResponderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workItemType = fake()->randomElement(['story', 'bug', 'ops_request']);
        $statuses = EventResponder::AVAILABLE_STATUSES[$workItemType];

        return [
            'agent_role_id' => AgentRole::factory(),
            'work_item_type' => $workItemType,
            'status' => fake()->randomElement($statuses),
            'instructions' => fake()->sentence(),
        ];
    }

    public function forAgentRole(AgentRole $agentRole): static
    {
        return $this->state(fn () => [
            'agent_role_id' => $agentRole->id,
        ]);
    }
}
