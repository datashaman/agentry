<?php

namespace App\Agents\Workflows;

use App\Agents\AgentResolver;
use App\Agents\Workflows\Prompts\EvaluatorPrompts;
use App\Models\Agent;
use App\Models\OpsRequest;
use App\Models\Team;
use Closure;

class EvaluatorOptimizerRunner
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
        $generatorAgentId = $config['generator_agent_id'] ?? null;
        $evaluatorAgentId = $config['evaluator_agent_id'] ?? null;
        $maxRefinements = $config['max_refinements'] ?? 3;
        $minRating = $config['min_rating'] ?? 'good';

        $generatorAgent = Agent::query()->find($generatorAgentId);
        $evaluatorAgent = Agent::query()->find($evaluatorAgentId);

        if ($generatorAgent === null || $evaluatorAgent === null) {
            return new WorkflowResult(response: '', metadata: ['error' => 'Generator or evaluator agent not found']);
        }

        $generatorConfig = $this->agentResolver->resolve($generatorAgent, $workItem);
        $evaluatorConfig = $this->agentResolver->resolve($evaluatorAgent, $workItem);
        $evaluatorConfig['instructions'] = EvaluatorPrompts::EVALUATION_SYSTEM;

        $steps = [];
        $currentInput = $request;
        $bestResponse = '';
        $bestRating = 'poor';

        for ($i = 0; $i <= $maxRefinements; $i++) {
            $response = $llmGateway($generatorConfig, $currentInput);
            $steps[] = [
                'agent_id' => $generatorAgent->id,
                'agent_name' => $generatorAgent->name,
                'input' => $currentInput,
                'output' => $response,
            ];

            $evaluationPrompt = EvaluatorPrompts::evaluationPrompt($request, $response);
            $evaluationResponse = $llmGateway($evaluatorConfig, $evaluationPrompt);
            $steps[] = [
                'agent_id' => $evaluatorAgent->id,
                'agent_name' => $evaluatorAgent->name,
                'input' => $evaluationPrompt,
                'output' => $evaluationResponse,
            ];

            $evaluation = json_decode($evaluationResponse, true);
            $rating = $evaluation['rating'] ?? 'poor';
            $feedback = $evaluation['feedback'] ?? '';

            if (EvaluatorPrompts::meetsThreshold($rating, $bestRating)) {
                $bestResponse = $response;
                $bestRating = $rating;
            }

            if (EvaluatorPrompts::meetsThreshold($rating, $minRating)) {
                return new WorkflowResult(
                    response: $response,
                    steps: $steps,
                    metadata: [
                        'workflow_type' => 'evaluator_optimizer',
                        'refinements' => $i,
                        'final_rating' => $rating,
                    ],
                );
            }

            if ($i < $maxRefinements) {
                $currentInput = EvaluatorPrompts::refinementPrompt($request, $response, $feedback);
            }
        }

        return new WorkflowResult(
            response: $bestResponse,
            steps: $steps,
            metadata: [
                'workflow_type' => 'evaluator_optimizer',
                'refinements' => $maxRefinements,
                'final_rating' => $bestRating,
                'threshold_met' => false,
            ],
        );
    }
}
