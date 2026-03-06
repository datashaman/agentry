<?php

use App\Agents\AgentResolver;
use App\Agents\ToolRegistry;
use App\Agents\Workflows\EvaluatorOptimizerRunner;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Team;

beforeEach(function () {
    $this->runner = new EvaluatorOptimizerRunner(new AgentResolver(new ToolRegistry));
    $this->org = Organization::factory()->create();
    $this->team = Team::factory()->create(['organization_id' => $this->org->id]);
    $this->agentRole = AgentRole::factory()->forOrganization($this->org)->create([
        'instructions' => 'Test agent',
        'tools' => [],
    ]);
});

test('evaluator optimizer returns on first attempt when quality meets threshold', function () {
    $generator = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Generator',
    ]);
    $evaluator = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Evaluator',
    ]);

    $this->team->update([
        'workflow_type' => 'evaluator_optimizer',
        'workflow_config' => [
            'generator_agent_id' => $generator->id,
            'evaluator_agent_id' => $evaluator->id,
            'max_refinements' => 3,
            'min_rating' => 'good',
        ],
    ]);

    $callCount = 0;
    $gateway = function ($config, $input) use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            return 'great response';
        }

        return json_encode(['rating' => 'excellent', 'feedback' => 'Perfect']);
    };

    $result = $this->runner->run($this->team, 'write something', $gateway);

    expect($result->response)->toBe('great response')
        ->and($result->metadata['refinements'])->toBe(0)
        ->and($result->metadata['final_rating'])->toBe('excellent');
});

test('evaluator optimizer refines until quality threshold met', function () {
    $generator = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Generator',
    ]);
    $evaluator = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Evaluator',
    ]);

    $this->team->update([
        'workflow_type' => 'evaluator_optimizer',
        'workflow_config' => [
            'generator_agent_id' => $generator->id,
            'evaluator_agent_id' => $evaluator->id,
            'max_refinements' => 3,
            'min_rating' => 'good',
        ],
    ]);

    $callCount = 0;
    $gateway = function ($config, $input) use (&$callCount) {
        $callCount++;

        // Generator calls: 1, 3
        // Evaluator calls: 2, 4
        if ($callCount === 1) {
            return 'first attempt';
        }
        if ($callCount === 2) {
            return json_encode(['rating' => 'poor', 'feedback' => 'Needs improvement']);
        }
        if ($callCount === 3) {
            return 'improved response';
        }

        return json_encode(['rating' => 'good', 'feedback' => 'Good enough']);
    };

    $result = $this->runner->run($this->team, 'write', $gateway);

    expect($result->response)->toBe('improved response')
        ->and($result->metadata['refinements'])->toBe(1)
        ->and($result->metadata['final_rating'])->toBe('good');
});

test('evaluator optimizer returns best response when max refinements exceeded', function () {
    $generator = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Generator',
    ]);
    $evaluator = Agent::factory()->create([
        'team_id' => $this->team->id,
        'agent_role_id' => $this->agentRole->id,
        'name' => 'Evaluator',
    ]);

    $this->team->update([
        'workflow_type' => 'evaluator_optimizer',
        'workflow_config' => [
            'generator_agent_id' => $generator->id,
            'evaluator_agent_id' => $evaluator->id,
            'max_refinements' => 1,
            'min_rating' => 'excellent',
        ],
    ]);

    $gateway = function ($config, $input) {
        if (str_contains($input, 'Evaluator feedback') || str_contains($input, 'write')) {
            return 'generated content';
        }

        return json_encode(['rating' => 'adequate', 'feedback' => 'Not excellent yet']);
    };

    $result = $this->runner->run($this->team, 'write', $gateway);

    expect($result->metadata['threshold_met'])->toBeFalse()
        ->and($result->response)->not->toBeEmpty();
});

test('evaluator optimizer returns error when agents not found', function () {
    $this->team->update([
        'workflow_type' => 'evaluator_optimizer',
        'workflow_config' => [
            'generator_agent_id' => 99999,
            'evaluator_agent_id' => 99998,
        ],
    ]);

    $result = $this->runner->run($this->team, 'test', fn () => '');

    expect($result->metadata['error'])->toBe('Generator or evaluator agent not found');
});
