<?php

use App\Agents\AgentResolver;
use App\Agents\Workflows\WorkflowResult;
use App\Agents\Workflows\WorkflowRunner;
use App\Jobs\RunAgentWork;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\EventResponder;
use App\Models\OpsRequest;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

test('runs workflow with responder instructions when team has workflow_type', function () {
    $team = Team::factory()->chain()->create();
    $agentRole = AgentRole::factory()->create();
    $agent = Agent::factory()->create(['team_id' => $team->id, 'agent_role_id' => $agentRole->id]);
    $opsRequest = OpsRequest::factory()->create(['assigned_agent_id' => $agent->id]);
    $responder = EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'ops_request',
        'status' => 'planning',
        'instructions' => 'Review the implementation carefully',
    ]);

    $workflowRunner = Mockery::mock(WorkflowRunner::class);
    $workflowRunner->shouldReceive('run')
        ->once()
        ->withArgs(function ($t, $request, $gateway, $workItem) use ($team, $opsRequest) {
            return $t->is($team)
                && $request === 'Review the implementation carefully'
                && is_callable($gateway)
                && $workItem->is($opsRequest);
        })
        ->andReturn(new WorkflowResult(response: 'done'));

    $agentResolver = Mockery::mock(AgentResolver::class);
    $agentResolver->shouldNotReceive('resolve');

    $job = new RunAgentWork($agent, $team, $opsRequest, $responder);
    $job->handle($agentResolver, $workflowRunner);
});

test('appends responder instructions to resolved config for single agent', function () {
    $team = Team::factory()->create(['workflow_type' => 'none']);
    $agentRole = AgentRole::factory()->create();
    $agent = Agent::factory()->create(['team_id' => $team->id, 'agent_role_id' => $agentRole->id]);
    $opsRequest = OpsRequest::factory()->create(['assigned_agent_id' => $agent->id]);
    $responder = EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'ops_request',
        'status' => 'planning',
        'instructions' => 'Plan the execution steps',
    ]);

    $resolvedConfig = [
        'instructions' => 'You are a code reviewer',
        'tools' => [],
        'model' => 'claude-sonnet-4-6',
        'provider' => 'anthropic',
        'temperature' => null,
        'max_steps' => null,
        'max_tokens' => null,
        'timeout' => null,
    ];

    $agentResolver = Mockery::mock(AgentResolver::class);
    $agentResolver->shouldReceive('resolve')
        ->once()
        ->with(Mockery::on(fn ($a) => $a->is($agent)), Mockery::on(fn ($s) => $s->is($opsRequest)))
        ->andReturn($resolvedConfig);

    $workflowRunner = Mockery::mock(WorkflowRunner::class);
    $workflowRunner->shouldNotReceive('run');

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($message, $context) {
            return $message === 'LLM gateway placeholder called'
                && $context['model'] === 'claude-sonnet-4-6';
        });

    $job = new RunAgentWork($agent, null, $opsRequest, $responder);
    $job->handle($agentResolver, $workflowRunner);
});

test('placeholder LLM gateway logs and returns empty string', function () {
    $team = Team::factory()->create(['workflow_type' => 'none']);
    $agentRole = AgentRole::factory()->create();
    $agent = Agent::factory()->create(['team_id' => $team->id, 'agent_role_id' => $agentRole->id]);
    $opsRequest = OpsRequest::factory()->create(['assigned_agent_id' => $agent->id]);
    $responder = EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'ops_request',
        'status' => 'verifying',
        'instructions' => 'Check test coverage',
    ]);

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($message) {
            return $message === 'LLM gateway placeholder called';
        });

    $agentResolver = Mockery::mock(AgentResolver::class);
    $agentResolver->shouldReceive('resolve')->andReturn([
        'instructions' => null,
        'tools' => [],
        'model' => 'test-model',
        'provider' => 'test',
        'temperature' => null,
        'max_steps' => null,
        'max_tokens' => null,
        'timeout' => null,
    ]);

    $workflowRunner = Mockery::mock(WorkflowRunner::class);

    $job = new RunAgentWork($agent, null, $opsRequest, $responder);
    $job->handle($agentResolver, $workflowRunner);
});
