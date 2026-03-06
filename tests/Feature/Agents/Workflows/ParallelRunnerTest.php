<?php

use App\Agents\AgentResolver;
use App\Agents\ToolRegistry;
use App\Agents\Workflows\ParallelRunner;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Team;

beforeEach(function () {
    $this->runner = new ParallelRunner(new AgentResolver(new ToolRegistry));
    $this->org = Organization::factory()->create();
    $this->team = Team::factory()->create(['organization_id' => $this->org->id]);
    $this->agentRole = AgentRole::factory()->forOrganization($this->org)->create([
        'instructions' => 'Test agent',
        'tools' => [],
    ]);
});

test('parallel runner sends same request to all agents', function () {
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
        'workflow_type' => 'parallel',
        'workflow_config' => ['agents' => [$agent1->id, $agent2->id]],
    ]);

    $inputs = [];
    $gateway = function ($config, $input) use (&$inputs) {
        $inputs[] = $input;

        return "response for {$input}";
    };

    $result = $this->runner->run($this->team, 'shared request', $gateway);

    expect($inputs)->each->toBe('shared request')
        ->and($result->steps)->toHaveCount(2)
        ->and($result->response)->toContain('[Agent A]')
        ->and($result->response)->toContain('[Agent B]')
        ->and($result->metadata['fan_in'])->toBeFalse();
});

test('parallel runner passes aggregated results to fan-in agent', function () {
    $agent1 = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Worker A',
    ]);
    $fanIn = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Synthesizer',
    ]);

    $this->team->update([
        'workflow_type' => 'parallel',
        'workflow_config' => [
            'agents' => [$agent1->id],
            'fan_in_agent_id' => $fanIn->id,
        ],
    ]);

    $callCount = 0;
    $gateway = function ($config, $input) use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            return 'worker output';
        }

        return "synthesized: {$input}";
    };

    $result = $this->runner->run($this->team, 'request', $gateway);

    expect($result->steps)->toHaveCount(2)
        ->and($result->steps[1]['agent_name'])->toBe('Synthesizer')
        ->and($result->metadata['fan_in'])->toBeTrue();
});
