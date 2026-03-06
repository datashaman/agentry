<?php

namespace App\Agents\Workflows\Prompts;

class OrchestratorPrompts
{
    public const SYSTEM = <<<'PROMPT'
You are an orchestration agent that breaks down complex tasks and delegates them to worker agents. You plan, delegate, and synthesize results.

For each iteration, respond with a JSON object:
{
    "is_complete": false,
    "next_task": {
        "agent_id": <id>,
        "instruction": "<what this agent should do>"
    },
    "reasoning": "<why this step is needed>"
}

When the overall task is complete, respond with:
{
    "is_complete": true,
    "final_response": "<synthesized result>"
}
PROMPT;

    /**
     * @param  array<int, array{id: int, name: string, instructions: string|null}>  $agents
     * @param  list<array{agent_id: int, agent_name: string, input: string, output: string}>  $completedSteps
     */
    public static function iterationPrompt(string $request, array $agents, array $completedSteps): string
    {
        $agentList = '';
        foreach ($agents as $agent) {
            $desc = $agent['instructions'] ? ' - '.substr($agent['instructions'], 0, 200) : '';
            $agentList .= "Agent {$agent['id']}: {$agent['name']}{$desc}\n";
        }

        $history = '';
        foreach ($completedSteps as $step) {
            $history .= "- Agent {$step['agent_id']} ({$step['agent_name']}): {$step['input']} => {$step['output']}\n";
        }

        return <<<PROMPT
Original request: {$request}

Available worker agents:
{$agentList}

Completed steps:
{$history}

What should happen next? Respond with JSON only.
PROMPT;
    }
}
