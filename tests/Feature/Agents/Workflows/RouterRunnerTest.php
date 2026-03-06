<?php

use App\Agents\AgentResolver;
use App\Agents\ToolRegistry;
use App\Agents\Workflows\RouterRunner;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Team;

beforeEach(function () {
    $this->runner = new RouterRunner(new AgentResolver(new ToolRegistry));
    $this->org = Organization::factory()->create();
    $this->team = Team::factory()->create(['organization_id' => $this->org->id]);
    $this->agentRole = AgentRole::factory()->forOrganization($this->org)->create([
        'instructions' => 'Test agent',
        'tools' => [],
    ]);
});

test('router runner routes request to selected agent', function () {
    $routerAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Router',
    ]);
    $workerAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Worker',
    ]);

    $this->team->update([
        'workflow_type' => 'router',
        'workflow_config' => [
            'router_agent_id' => $routerAgent->id,
            'agents' => [$workerAgent->id],
        ],
    ]);

    $callCount = 0;
    $gateway = function ($config, $input) use (&$callCount, $workerAgent) {
        $callCount++;
        if ($callCount === 1) {
            return (string) $workerAgent->id;
        }

        return "handled: {$input}";
    };

    $result = $this->runner->run($this->team, 'route me', $gateway);

    expect($result->steps)->toHaveCount(2)
        ->and($result->steps[0]['agent_name'])->toBe('Router')
        ->and($result->steps[1]['agent_name'])->toBe('Worker')
        ->and($result->response)->toBe('handled: route me')
        ->and($result->metadata['routed_to'])->toBe($workerAgent->id);
});

test('router runner returns error when router agent not found', function () {
    $this->team->update([
        'workflow_type' => 'router',
        'workflow_config' => [
            'router_agent_id' => 99999,
            'agents' => [],
        ],
    ]);

    $result = $this->runner->run($this->team, 'test', fn () => '');

    expect($result->response)->toBe('')
        ->and($result->metadata['error'])->toBe('Router agent not found');
});

test('router runner returns error when selected agent not in team', function () {
    $routerAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Router',
    ]);

    $this->team->update([
        'workflow_type' => 'router',
        'workflow_config' => [
            'router_agent_id' => $routerAgent->id,
            'agents' => [],
        ],
    ]);

    $gateway = fn () => '99999';

    $result = $this->runner->run($this->team, 'test', $gateway);

    expect($result->steps)->toHaveCount(1)
        ->and($result->metadata['error'])->toContain('not found in team');
});
