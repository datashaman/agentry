<?php

use App\Agents\AgentResolver;
use App\Agents\ToolRegistry;
use App\Agents\Workflows\ChainRunner;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Team;

beforeEach(function () {
    $this->runner = new ChainRunner(new AgentResolver(new ToolRegistry));
    $this->org = Organization::factory()->create();
    $this->team = Team::factory()->create(['organization_id' => $this->org->id]);
    $this->agentRole = AgentRole::factory()->forOrganization($this->org)->create([
        'instructions' => 'Test agent',
        'tools' => [],
    ]);
});

test('chain runner executes agents in order passing output as next input', function () {
    $agent1 = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Agent A',
    ]);
    $agent2 = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Agent B',
    ]);

    $this->team->update([
        'workflow_type' => 'chain',
        'workflow_config' => ['agents' => [$agent1->id, $agent2->id], 'cumulative' => false],
    ]);

    $callLog = [];
    $gateway = function ($config, $input) use (&$callLog) {
        $callLog[] = $input;

        return "processed: {$input}";
    };

    $result = $this->runner->run($this->team, 'start', $gateway);

    expect($callLog)->toHaveCount(2)
        ->and($callLog[0])->toBe('start')
        ->and($callLog[1])->toBe('processed: start')
        ->and($result->response)->toBe('processed: processed: start')
        ->and($result->steps)->toHaveCount(2)
        ->and($result->steps[0]['agent_name'])->toBe('Agent A')
        ->and($result->steps[1]['agent_name'])->toBe('Agent B');
});

test('chain runner supports cumulative mode', function () {
    $agent1 = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Agent A',
    ]);
    $agent2 = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Agent B',
    ]);

    $this->team->update([
        'workflow_type' => 'chain',
        'workflow_config' => ['agents' => [$agent1->id, $agent2->id], 'cumulative' => true],
    ]);

    $callLog = [];
    $gateway = function ($config, $input) use (&$callLog) {
        $callLog[] = $input;

        return 'output';
    };

    $this->runner->run($this->team, 'original', $gateway);

    expect($callLog[1])->toContain('Original request: original')
        ->and($callLog[1])->toContain('[Agent A]: output');
});

test('chain runner returns empty response when no agents configured', function () {
    $this->team->update([
        'workflow_type' => 'chain',
        'workflow_config' => ['agents' => [], 'cumulative' => false],
    ]);

    $result = $this->runner->run($this->team, 'test', fn () => 'response');

    expect($result->response)->toBe('')
        ->and($result->steps)->toBe([]);
});

test('chain runner skips non-existent agent IDs', function () {
    $agent1 = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Agent A',
    ]);

    $this->team->update([
        'workflow_type' => 'chain',
        'workflow_config' => ['agents' => [99999, $agent1->id], 'cumulative' => false],
    ]);

    $result = $this->runner->run($this->team, 'test', fn ($c, $i) => "done: {$i}");

    expect($result->steps)->toHaveCount(1)
        ->and($result->steps[0]['agent_name'])->toBe('Agent A');
});
