<?php

use App\Events\OpsRequestTransitioned;
use App\Jobs\RunAgentWork;
use App\Listeners\DispatchAgentWork;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\EventResponder;
use App\Models\OpsRequest;
use App\Models\Team;
use Illuminate\Support\Facades\Queue;

test('dispatches RunAgentWork when agent role has matching event responder', function () {
    Queue::fake();

    $agentRole = AgentRole::factory()->create();
    $responder = EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'ops_request',
        'status' => 'planning',
        'instructions' => 'Plan the ops request execution',
    ]);

    $team = Team::factory()->create(['workflow_type' => 'none']);
    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id, 'team_id' => $team->id]);
    $opsRequest = OpsRequest::factory()->create([
        'status' => 'planning',
        'assigned_agent_id' => $agent->id,
    ]);

    $event = new OpsRequestTransitioned($opsRequest, 'triaged', 'planning');
    (new DispatchAgentWork)->handle($event);

    Queue::assertPushed(RunAgentWork::class, function (RunAgentWork $job) use ($agent, $opsRequest, $responder) {
        return $job->agent->is($agent)
            && $job->team === null
            && $job->workItem->is($opsRequest)
            && $job->responder->is($responder);
    });
});

test('dispatches with team when team has workflow', function () {
    Queue::fake();

    $agentRole = AgentRole::factory()->create();
    EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'ops_request',
        'status' => 'planning',
    ]);

    $team = Team::factory()->chain()->create();
    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id, 'team_id' => $team->id]);
    $opsRequest = OpsRequest::factory()->create([
        'status' => 'planning',
        'assigned_agent_id' => $agent->id,
    ]);

    $event = new OpsRequestTransitioned($opsRequest, 'triaged', 'planning');
    (new DispatchAgentWork)->handle($event);

    Queue::assertPushed(RunAgentWork::class, function (RunAgentWork $job) use ($team) {
        return $job->team !== null && $job->team->is($team);
    });
});

test('does not dispatch when agent role has no matching event responder', function () {
    Queue::fake();

    $agentRole = AgentRole::factory()->create();
    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id]);
    $opsRequest = OpsRequest::factory()->create([
        'status' => 'planning',
        'assigned_agent_id' => $agent->id,
    ]);

    $event = new OpsRequestTransitioned($opsRequest, 'triaged', 'planning');
    (new DispatchAgentWork)->handle($event);

    Queue::assertNothingPushed();
});

test('does not dispatch when no agent is assigned', function () {
    Queue::fake();

    $opsRequest = OpsRequest::factory()->create([
        'status' => 'planning',
        'assigned_agent_id' => null,
    ]);

    $event = new OpsRequestTransitioned($opsRequest, 'triaged', 'planning');
    (new DispatchAgentWork)->handle($event);

    Queue::assertNothingPushed();
});

test('team workflow falls back to agent-only when team has no workflow', function () {
    Queue::fake();

    $agentRole = AgentRole::factory()->create();
    EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'ops_request',
        'status' => 'planning',
    ]);

    $team = Team::factory()->create(['workflow_type' => 'none']);
    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id, 'team_id' => $team->id]);
    $opsRequest = OpsRequest::factory()->create([
        'status' => 'planning',
        'assigned_agent_id' => $agent->id,
    ]);

    $event = new OpsRequestTransitioned($opsRequest, 'triaged', 'planning');
    (new DispatchAgentWork)->handle($event);

    Queue::assertPushed(RunAgentWork::class, function (RunAgentWork $job) {
        return $job->team === null;
    });
});
