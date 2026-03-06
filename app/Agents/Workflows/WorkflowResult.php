<?php

namespace App\Agents\Workflows;

class WorkflowResult
{
    /**
     * @param  list<array{agent_id: int, agent_name: string, input: string, output: string}>  $steps
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $response,
        public readonly array $steps = [],
        public readonly array $metadata = [],
    ) {}
}
