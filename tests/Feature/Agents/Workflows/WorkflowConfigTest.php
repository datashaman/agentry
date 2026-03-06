<?php

use App\Agents\Workflows\Prompts\EvaluatorPrompts;
use App\Models\Organization;
use App\Models\Team;

test('team persists workflow type and config', function () {
    $org = Organization::factory()->create();
    $team = Team::factory()->create([
        'organization_id' => $org->id,
        'workflow_type' => 'chain',
        'workflow_config' => ['agents' => [1, 2, 3], 'cumulative' => true],
    ]);

    $team->refresh();

    expect($team->workflow_type)->toBe('chain')
        ->and($team->workflow_config)->toBe(['agents' => [1, 2, 3], 'cumulative' => true]);
});

test('team defaults to none workflow type', function () {
    $org = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $org->id]);

    expect($team->workflow_type)->toBe('none')
        ->and($team->workflow_config)->toBeNull();
});

test('team factory chain state works', function () {
    $team = Team::factory()->chain([1, 2], true)->create();

    expect($team->workflow_type)->toBe('chain')
        ->and($team->workflow_config['agents'])->toBe([1, 2])
        ->and($team->workflow_config['cumulative'])->toBeTrue();
});

test('team factory parallel state works', function () {
    $team = Team::factory()->parallel([1, 2], 3)->create();

    expect($team->workflow_type)->toBe('parallel')
        ->and($team->workflow_config['agents'])->toBe([1, 2])
        ->and($team->workflow_config['fan_in_agent_id'])->toBe(3);
});

test('team factory router state works', function () {
    $team = Team::factory()->router(1, [2, 3])->create();

    expect($team->workflow_type)->toBe('router')
        ->and($team->workflow_config['router_agent_id'])->toBe(1)
        ->and($team->workflow_config['agents'])->toBe([2, 3]);
});

test('team factory orchestrator state works', function () {
    $team = Team::factory()->orchestrator(1, [2, 3], 5)->create();

    expect($team->workflow_type)->toBe('orchestrator')
        ->and($team->workflow_config['planner_agent_id'])->toBe(1)
        ->and($team->workflow_config['agents'])->toBe([2, 3])
        ->and($team->workflow_config['max_iterations'])->toBe(5);
});

test('team factory evaluator optimizer state works', function () {
    $team = Team::factory()->evaluatorOptimizer(1, 2, 5, 'excellent')->create();

    expect($team->workflow_type)->toBe('evaluator_optimizer')
        ->and($team->workflow_config['generator_agent_id'])->toBe(1)
        ->and($team->workflow_config['evaluator_agent_id'])->toBe(2)
        ->and($team->workflow_config['max_refinements'])->toBe(5)
        ->and($team->workflow_config['min_rating'])->toBe('excellent');
});

test('evaluator prompts meets threshold logic', function () {
    expect(EvaluatorPrompts::meetsThreshold('excellent', 'good'))->toBeTrue()
        ->and(EvaluatorPrompts::meetsThreshold('good', 'good'))->toBeTrue()
        ->and(EvaluatorPrompts::meetsThreshold('adequate', 'good'))->toBeFalse()
        ->and(EvaluatorPrompts::meetsThreshold('poor', 'good'))->toBeFalse()
        ->and(EvaluatorPrompts::meetsThreshold('excellent', 'excellent'))->toBeTrue()
        ->and(EvaluatorPrompts::meetsThreshold('good', 'excellent'))->toBeFalse()
        ->and(EvaluatorPrompts::meetsThreshold('invalid', 'good'))->toBeFalse();
});
