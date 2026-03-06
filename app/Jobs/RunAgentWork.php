<?php

namespace App\Jobs;

use App\Agents\AgentResolver;
use App\Agents\Workflows\WorkflowRunner;
use App\Models\Agent;
use App\Models\Bug;
use App\Models\EventResponder;
use App\Models\OpsRequest;
use App\Models\Story;
use App\Models\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunAgentWork implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Agent $agent,
        public ?Team $team,
        public Story|Bug|OpsRequest $workItem,
        public EventResponder $responder,
    ) {}

    public function handle(AgentResolver $agentResolver, WorkflowRunner $workflowRunner): void
    {
        $llmGateway = function (array $config, string $prompt): string {
            Log::info('LLM gateway placeholder called', [
                'model' => $config['model'] ?? 'unknown',
                'provider' => $config['provider'] ?? 'unknown',
                'prompt_length' => strlen($prompt),
            ]);

            return '';
        };

        if ($this->team && $this->team->workflow_type !== 'none') {
            $workflowRunner->run(
                $this->team,
                $this->responder->instructions,
                $llmGateway,
                $this->workItem,
            );

            return;
        }

        $config = $agentResolver->resolve($this->agent, $this->workItem);

        $instructions = $config['instructions'] ?? '';
        $instructions = trim($instructions."\n\n".$this->responder->instructions);
        $config['instructions'] = $instructions;

        $llmGateway($config, $this->responder->instructions);
    }
}
