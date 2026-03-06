<?php

namespace App\Jobs;

use App\Agents\Workflows\WorkflowRunner;
use App\Models\Team;
use App\Models\WorkItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunTeamWork implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Team $team,
        public WorkItem $workItem,
    ) {}

    public function handle(WorkflowRunner $workflowRunner): void
    {
        $llmGateway = function (array $config, string $prompt): string {
            Log::info('LLM gateway placeholder called', [
                'model' => $config['model'] ?? 'unknown',
                'provider' => $config['provider'] ?? 'unknown',
                'prompt_length' => strlen($prompt),
            ]);

            return '';
        };

        $request = $this->buildRequest();

        $workflowRunner->run(
            $this->team,
            $request,
            $llmGateway,
        );
    }

    protected function buildRequest(): string
    {
        $this->workItem->loadMissing('conversation.messages');

        $parts = [];
        $parts[] = "Work Item: {$this->workItem->title}";

        if ($this->workItem->description) {
            $parts[] = "Description: {$this->workItem->description}";
        }

        if ($this->workItem->classified_type) {
            $parts[] = "Type: {$this->workItem->classified_type}";
        }

        if ($this->workItem->conversation) {
            foreach ($this->workItem->conversation->messages as $message) {
                $parts[] = "[{$message->role}]: {$message->content}";
            }
        }

        return implode("\n\n", $parts);
    }
}
