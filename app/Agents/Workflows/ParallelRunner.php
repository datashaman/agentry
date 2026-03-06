<?php

namespace App\Agents\Workflows;

use App\Agents\AgentResolver;
use App\Models\Agent;
use App\Models\OpsRequest;
use App\Models\Team;
use Closure;

class ParallelRunner
{
    public function __construct(
        protected AgentResolver $agentResolver,
    ) {}

    /**
     * @param  Closure(array, string): string  $llmGateway
     */
    public function run(Team $team, string $request, Closure $llmGateway, ?OpsRequest $workItem = null): WorkflowResult
    {
        $config = $team->workflow_config ?? [];
        $agentIds = $config['agents'] ?? [];
        $fanInAgentId = $config['fan_in_agent_id'] ?? null;

        $agents = Agent::query()->whereIn('id', $agentIds)->get()->keyBy('id');
        $steps = [];

        foreach ($agentIds as $agentId) {
            $agent = $agents->get($agentId);
            if ($agent === null) {
                continue;
            }

            $resolvedConfig = $this->agentResolver->resolve($agent, $workItem);
            $response = $llmGateway($resolvedConfig, $request);

            $steps[] = [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'input' => $request,
                'output' => $response,
            ];
        }

        $aggregated = $this->aggregateResults($steps);

        if ($fanInAgentId !== null) {
            $fanInAgent = Agent::query()->find($fanInAgentId);
            if ($fanInAgent !== null) {
                $fanInConfig = $this->agentResolver->resolve($fanInAgent, $workItem);
                $fanInInput = "Original request: {$request}\n\nAgent responses:\n{$aggregated}";
                $finalResponse = $llmGateway($fanInConfig, $fanInInput);

                $steps[] = [
                    'agent_id' => $fanInAgent->id,
                    'agent_name' => $fanInAgent->name,
                    'input' => $fanInInput,
                    'output' => $finalResponse,
                ];

                return new WorkflowResult(
                    response: $finalResponse,
                    steps: $steps,
                    metadata: ['workflow_type' => 'parallel', 'fan_in' => true],
                );
            }
        }

        return new WorkflowResult(
            response: $aggregated,
            steps: $steps,
            metadata: ['workflow_type' => 'parallel', 'fan_in' => false],
        );
    }

    /**
     * @param  list<array{agent_id: int, agent_name: string, input: string, output: string}>  $steps
     */
    protected function aggregateResults(array $steps): string
    {
        $parts = [];
        foreach ($steps as $step) {
            $parts[] = "[{$step['agent_name']}]: {$step['output']}";
        }

        return implode("\n\n", $parts);
    }
}
