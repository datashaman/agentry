<?php

namespace App\Agents\Workflows;

use App\Agents\AgentResolver;
use App\Models\Agent;
use App\Models\OpsRequest;
use App\Models\Team;
use Closure;

class ChainRunner
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
        $cumulative = $config['cumulative'] ?? false;

        $agents = Agent::query()->whereIn('id', $agentIds)->get()->keyBy('id');
        $steps = [];
        $currentInput = $request;

        foreach ($agentIds as $agentId) {
            $agent = $agents->get($agentId);
            if ($agent === null) {
                continue;
            }

            $resolvedConfig = $this->agentResolver->resolve($agent, $workItem);
            $response = $llmGateway($resolvedConfig, $currentInput);

            $steps[] = [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'input' => $currentInput,
                'output' => $response,
            ];

            if ($cumulative) {
                $currentInput = $this->buildCumulativeInput($request, $steps);
            } else {
                $currentInput = $response;
            }
        }

        $lastStep = end($steps);

        return new WorkflowResult(
            response: $lastStep ? $lastStep['output'] : '',
            steps: $steps,
            metadata: ['workflow_type' => 'chain', 'cumulative' => $cumulative],
        );
    }

    /**
     * @param  list<array{agent_id: int, agent_name: string, input: string, output: string}>  $steps
     */
    protected function buildCumulativeInput(string $originalRequest, array $steps): string
    {
        $parts = ["Original request: {$originalRequest}"];
        foreach ($steps as $step) {
            $parts[] = "[{$step['agent_name']}]: {$step['output']}";
        }

        return implode("\n\n", $parts);
    }
}
