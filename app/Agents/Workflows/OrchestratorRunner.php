<?php

namespace App\Agents\Workflows;

use App\Agents\AgentResolver;
use App\Agents\Workflows\Prompts\OrchestratorPrompts;
use App\Models\Agent;
use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Story;
use App\Models\Team;
use Closure;

class OrchestratorRunner
{
    public function __construct(
        protected AgentResolver $agentResolver,
    ) {}

    /**
     * @param  Closure(array, string): string  $llmGateway
     */
    public function run(Team $team, string $request, Closure $llmGateway, Story|Bug|OpsRequest|null $workItem = null): WorkflowResult
    {
        $config = $team->workflow_config ?? [];
        $plannerAgentId = $config['planner_agent_id'] ?? null;
        $agentIds = $config['agents'] ?? [];
        $maxIterations = $config['max_iterations'] ?? 10;

        $plannerAgent = Agent::query()->find($plannerAgentId);
        if ($plannerAgent === null) {
            return new WorkflowResult(response: '', metadata: ['error' => 'Planner agent not found']);
        }

        $workers = Agent::query()->whereIn('id', $agentIds)->get();
        $workerDescriptions = $workers->map(fn (Agent $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'instructions' => $a->agentRole?->instructions,
        ])->values()->toArray();

        $plannerConfig = $this->agentResolver->resolve($plannerAgent, $workItem);
        $plannerConfig['instructions'] = OrchestratorPrompts::SYSTEM;

        $steps = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            $iterationPrompt = OrchestratorPrompts::iterationPrompt($request, $workerDescriptions, $steps);
            $plannerResponse = $llmGateway($plannerConfig, $iterationPrompt);

            $decision = json_decode($plannerResponse, true);
            if (! is_array($decision)) {
                return new WorkflowResult(
                    response: $plannerResponse,
                    steps: $steps,
                    metadata: ['workflow_type' => 'orchestrator', 'error' => 'Invalid planner response'],
                );
            }

            if ($decision['is_complete'] ?? false) {
                return new WorkflowResult(
                    response: $decision['final_response'] ?? '',
                    steps: $steps,
                    metadata: ['workflow_type' => 'orchestrator', 'iterations' => $i + 1],
                );
            }

            $nextTask = $decision['next_task'] ?? null;
            if ($nextTask === null) {
                break;
            }

            $workerAgent = $workers->firstWhere('id', $nextTask['agent_id']);
            if ($workerAgent === null) {
                continue;
            }

            $workerConfig = $this->agentResolver->resolve($workerAgent, $workItem);
            $workerResponse = $llmGateway($workerConfig, $nextTask['instruction']);

            $steps[] = [
                'agent_id' => $workerAgent->id,
                'agent_name' => $workerAgent->name,
                'input' => $nextTask['instruction'],
                'output' => $workerResponse,
            ];
        }

        return new WorkflowResult(
            response: end($steps) ? end($steps)['output'] : '',
            steps: $steps,
            metadata: ['workflow_type' => 'orchestrator', 'iterations' => count($steps), 'max_iterations_reached' => true],
        );
    }
}
