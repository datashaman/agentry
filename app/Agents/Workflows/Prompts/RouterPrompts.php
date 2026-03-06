<?php

namespace App\Agents\Workflows\Prompts;

class RouterPrompts
{
    public const SYSTEM = <<<'PROMPT'
You are a routing agent. Your job is to analyze the incoming request and select the single most appropriate agent to handle it.

You will be given a list of available agents with their descriptions. Respond with ONLY the agent ID number that should handle the request. Do not explain your reasoning.
PROMPT;

    /**
     * @param  array<int, array{id: int, name: string, instructions: string|null}>  $agents
     */
    public static function selectionPrompt(string $request, array $agents): string
    {
        $agentList = '';
        foreach ($agents as $agent) {
            $desc = $agent['instructions'] ? ' - '.substr($agent['instructions'], 0, 200) : '';
            $agentList .= "Agent {$agent['id']}: {$agent['name']}{$desc}\n";
        }

        return <<<PROMPT
Available agents:
{$agentList}
Request: {$request}

Respond with ONLY the agent ID number.
PROMPT;
    }
}
