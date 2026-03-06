<?php

use App\Agents\AgentResolver;
use App\Agents\ToolRegistry;
use App\Agents\Workflows\OrchestratorRunner;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Team;

beforeEach(function () {
    $this->runner = new OrchestratorRunner(new AgentResolver(new ToolRegistry));
    $this->org = Organization::factory()->create();
    $this->team = Team::factory()->create(['organization_id' => $this->org->id]);
    $this->agentRole = AgentRole::factory()->forOrganization($this->org)->create([
        'instructions' => 'Test agent',
        'tools' => [],
    ]);
});

test('orchestrator completes after planner signals done', function () {
    $planner = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Planner',
    ]);
    $worker = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Worker',
    ]);

    $this->team->update([
        'workflow_type' => 'orchestrator',
        'workflow_config' => [
            'planner_agent_id' => $planner->id,
            'agents' => [$worker->id],
            'max_iterations' => 10,
        ],
    ]);

    $callCount = 0;
    $gateway = function ($config, $input) use (&$callCount, $worker) {
        $callCount++;
        if ($callCount === 1) {
            return json_encode([
                'is_complete' => false,
                'next_task' => ['agent_id' => $worker->id, 'instruction' => 'Do work'],
                'reasoning' => 'Need to do work first',
            ]);
        }
        if ($callCount === 2) {
            return 'work done';
        }

        return json_encode([
            'is_complete' => true,
            'final_response' => 'All tasks completed',
        ]);
    };

    $result = $this->runner->run($this->team, 'complex task', $gateway);

    expect($result->response)->toBe('All tasks completed')
        ->and($result->steps)->toHaveCount(1)
        ->and($result->steps[0]['agent_name'])->toBe('Worker')
        ->and($result->metadata['workflow_type'])->toBe('orchestrator')
        ->and($result->metadata['iterations'])->toBe(2);
});

test('orchestrator stops at max iterations', function () {
    $planner = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Planner',
    ]);
    $worker = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Worker',
    ]);

    $this->team->update([
        'workflow_type' => 'orchestrator',
        'workflow_config' => [
            'planner_agent_id' => $planner->id,
            'agents' => [$worker->id],
            'max_iterations' => 2,
        ],
    ]);

    $gateway = function ($config, $input) use ($worker) {
        if (str_contains($input, 'Original request')) {
            return json_encode([
                'is_complete' => false,
                'next_task' => ['agent_id' => $worker->id, 'instruction' => 'Do work'],
            ]);
        }

        return 'worker output';
    };

    $result = $this->runner->run($this->team, 'task', $gateway);

    expect($result->metadata['max_iterations_reached'])->toBeTrue();
});

test('orchestrator returns error when planner agent not found', function () {
    $this->team->update([
        'workflow_type' => 'orchestrator',
        'workflow_config' => [
            'planner_agent_id' => 99999,
            'agents' => [],
        ],
    ]);

    $result = $this->runner->run($this->team, 'test', fn () => '');

    expect($result->metadata['error'])->toBe('Planner agent not found');
});
