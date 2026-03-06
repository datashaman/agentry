<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'workflow_type' => 'none',
        ];
    }

    public function chain(array $agentIds = [], bool $cumulative = false): static
    {
        return $this->state(fn () => [
            'workflow_type' => 'chain',
            'workflow_config' => ['agents' => $agentIds, 'cumulative' => $cumulative],
        ]);
    }

    public function parallel(array $agentIds = [], ?int $fanInAgentId = null): static
    {
        return $this->state(fn () => [
            'workflow_type' => 'parallel',
            'workflow_config' => array_filter([
                'agents' => $agentIds,
                'fan_in_agent_id' => $fanInAgentId,
            ], fn ($v) => $v !== null),
        ]);
    }

    public function router(int $routerAgentId, array $agentIds = []): static
    {
        return $this->state(fn () => [
            'workflow_type' => 'router',
            'workflow_config' => ['router_agent_id' => $routerAgentId, 'agents' => $agentIds],
        ]);
    }

    public function orchestrator(int $plannerAgentId, array $agentIds = [], int $maxIterations = 10): static
    {
        return $this->state(fn () => [
            'workflow_type' => 'orchestrator',
            'workflow_config' => [
                'planner_agent_id' => $plannerAgentId,
                'agents' => $agentIds,
                'max_iterations' => $maxIterations,
            ],
        ]);
    }

    public function evaluatorOptimizer(int $generatorAgentId, int $evaluatorAgentId, int $maxRefinements = 3, string $minRating = 'good'): static
    {
        return $this->state(fn () => [
            'workflow_type' => 'evaluator_optimizer',
            'workflow_config' => [
                'generator_agent_id' => $generatorAgentId,
                'evaluator_agent_id' => $evaluatorAgentId,
                'max_refinements' => $maxRefinements,
                'min_rating' => $minRating,
            ],
        ]);
    }
}
