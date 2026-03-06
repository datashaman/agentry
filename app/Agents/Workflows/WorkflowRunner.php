<?php

namespace App\Agents\Workflows;

use App\Agents\AgentResolver;
use App\Models\OpsRequest;
use App\Models\Team;
use Closure;

class WorkflowRunner
{
    public function __construct(
        protected AgentResolver $agentResolver,
    ) {}

    /**
     * Run the team's workflow against a request.
     *
     * @param  Closure(array, string): string  $llmGateway  Receives resolved agent config + prompt, returns response string.
     */
    public function run(Team $team, string $request, Closure $llmGateway, ?OpsRequest $workItem = null): WorkflowResult
    {
        $team->loadMissing('agents');

        return match ($team->workflow_type) {
            'chain' => (new ChainRunner($this->agentResolver))->run($team, $request, $llmGateway, $workItem),
            'parallel' => (new ParallelRunner($this->agentResolver))->run($team, $request, $llmGateway, $workItem),
            'router' => (new RouterRunner($this->agentResolver))->run($team, $request, $llmGateway, $workItem),
            'orchestrator' => (new OrchestratorRunner($this->agentResolver))->run($team, $request, $llmGateway, $workItem),
            'evaluator_optimizer' => (new EvaluatorOptimizerRunner($this->agentResolver))->run($team, $request, $llmGateway, $workItem),
            default => new WorkflowResult(
                response: '',
                steps: [],
                metadata: ['workflow_type' => 'none'],
            ),
        };
    }
}
