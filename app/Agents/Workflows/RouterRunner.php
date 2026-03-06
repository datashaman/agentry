<?php

namespace App\Agents\Workflows;

use App\Agents\AgentResolver;
use App\Agents\Workflows\Prompts\RouterPrompts;
use App\Models\Agent;
use App\Models\OpsRequest;
use App\Models\Team;
use Closure;

class RouterRunner
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
        $routerAgentId = $config['router_agent_id'] ?? null;
        $agentIds = $config['agents'] ?? [];

        $routerAgent = Agent::query()->find($routerAgentId);
        if ($routerAgent === null) {
            return new WorkflowResult(response: '', metadata: ['error' => 'Router agent not found']);
        }

        $agents = Agent::query()->whereIn('id', $agentIds)->get();
        $agentDescriptions = $agents->map(fn (Agent $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'instructions' => $a->agentRole?->instructions,
        ])->values()->toArray();

        $routerConfig = $this->agentResolver->resolve($routerAgent, $workItem);
        $routerConfig['instructions'] = RouterPrompts::SYSTEM;
        $selectionPrompt = RouterPrompts::selectionPrompt($request, $agentDescriptions);
        $routingDecision = trim($llmGateway($routerConfig, $selectionPrompt));

        $selectedId = (int) preg_replace('/\D/', '', $routingDecision);
        $selectedAgent = $agents->firstWhere('id', $selectedId);

        $steps = [
            [
                'agent_id' => $routerAgent->id,
                'agent_name' => $routerAgent->name,
                'input' => $selectionPrompt,
                'output' => $routingDecision,
            ],
        ];

        if ($selectedAgent === null) {
            return new WorkflowResult(
                response: '',
                steps: $steps,
                metadata: ['workflow_type' => 'router', 'error' => "Agent {$selectedId} not found in team"],
            );
        }

        $selectedConfig = $this->agentResolver->resolve($selectedAgent, $workItem);
        $response = $llmGateway($selectedConfig, $request);

        $steps[] = [
            'agent_id' => $selectedAgent->id,
            'agent_name' => $selectedAgent->name,
            'input' => $request,
            'output' => $response,
        ];

        return new WorkflowResult(
            response: $response,
            steps: $steps,
            metadata: ['workflow_type' => 'router', 'routed_to' => $selectedId],
        );
    }
}
