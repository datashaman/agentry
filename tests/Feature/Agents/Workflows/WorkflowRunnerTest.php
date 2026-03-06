<?php

use App\Agents\AgentResolver;
use App\Agents\ToolRegistry;
use App\Agents\Workflows\WorkflowResult;
use App\Agents\Workflows\WorkflowRunner;
use App\Models\Organization;
use App\Models\Team;

beforeEach(function () {
    $this->runner = new WorkflowRunner(new AgentResolver(new ToolRegistry));
});

test('runner returns empty result for none workflow type', function () {
    $org = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $org->id]);

    $result = $this->runner->run($team, 'test request', fn () => 'response');

    expect($result)->toBeInstanceOf(WorkflowResult::class)
        ->and($result->response)->toBe('')
        ->and($result->steps)->toBe([])
        ->and($result->metadata['workflow_type'])->toBe('none');
});

test('runner delegates to chain runner for chain workflow type', function () {
    $org = Organization::factory()->create();
    $team = Team::factory()->create([
        'organization_id' => $org->id,
        'workflow_type' => 'chain',
        'workflow_config' => ['agents' => [], 'cumulative' => false],
    ]);

    $result = $this->runner->run($team, 'test', fn () => 'resp');

    expect($result->metadata['workflow_type'])->toBe('chain');
});
