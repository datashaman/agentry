<?php

namespace App\Jobs;

use App\Agents\AgentResolver;
use App\Models\Agent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunScheduledAgentWork implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Agent $agent,
    ) {}

    public function handle(AgentResolver $agentResolver): void
    {
        $config = $agentResolver->resolve($this->agent);

        $instructions = trim(($config['instructions'] ?? '')."\n\n".$this->agent->scheduled_instructions);
        $config['instructions'] = $instructions;

        Log::info('Scheduled agent work dispatched', [
            'agent' => $this->agent->name,
            'model' => $config['model'] ?? 'unknown',
            'provider' => $config['provider'] ?? 'unknown',
        ]);

        $this->agent->update(['last_scheduled_at' => now()]);
    }
}
