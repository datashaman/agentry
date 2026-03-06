<?php

use App\Events\BugTransitioned;
use App\Events\OpsRequestTransitioned;
use App\Events\StoryTransitioned;
use App\Jobs\RunAgentWork;
use App\Listeners\DispatchAgentWork;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Bug;
use App\Models\EventResponder;
use App\Models\OpsRequest;
use App\Models\Story;
use App\Models\Team;
use Illuminate\Support\Facades\Queue;

test('dispatches RunAgentWork when agent role has matching event responder', function () {
    Queue::fake();

    $agentRole = AgentRole::factory()->create();
    $responder = EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'story',
        'status' => 'spec_critique',
        'instructions' => 'Critique this spec for completeness',
    ]);

    $team = Team::factory()->create(['workflow_type' => 'none']);
    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id, 'team_id' => $team->id]);
    $story = Story::factory()->create([
        'status' => 'spec_critique',
        'assigned_agent_id' => $agent->id,
    ]);

    $event = new StoryTransitioned($story, 'backlog', 'spec_critique');
    (new DispatchAgentWork)->handle($event);

    Queue::assertPushed(RunAgentWork::class, function (RunAgentWork $job) use ($agent, $story, $responder) {
        return $job->agent->is($agent)
            && $job->team === null
            && $job->workItem->is($story)
            && $job->responder->is($responder);
    });
});

test('dispatches with team when team has workflow', function () {
    Queue::fake();

    $agentRole = AgentRole::factory()->create();
    EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'story',
        'status' => 'in_development',
    ]);

    $team = Team::factory()->chain()->create();
    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id, 'team_id' => $team->id]);
    $story = Story::factory()->create([
        'status' => 'in_development',
        'assigned_agent_id' => $agent->id,
    ]);

    $event = new StoryTransitioned($story, 'design_critique', 'in_development');
    (new DispatchAgentWork)->handle($event);

    Queue::assertPushed(RunAgentWork::class, function (RunAgentWork $job) use ($team) {
        return $job->team !== null && $job->team->is($team);
    });
});

test('does not dispatch when agent role has no matching event responder', function () {
    Queue::fake();

    $agentRole = AgentRole::factory()->create();
    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id]);
    $story = Story::factory()->create([
        'status' => 'refined',
        'assigned_agent_id' => $agent->id,
    ]);

    $event = new StoryTransitioned($story, 'backlog', 'refined');
    (new DispatchAgentWork)->handle($event);

    Queue::assertNothingPushed();
});

test('does not dispatch when no agent is assigned', function () {
    Queue::fake();

    $story = Story::factory()->create([
        'status' => 'spec_critique',
        'assigned_agent_id' => null,
    ]);

    $event = new StoryTransitioned($story, 'backlog', 'spec_critique');
    (new DispatchAgentWork)->handle($event);

    Queue::assertNothingPushed();
});

test('dispatches for matching bug event responder', function () {
    Queue::fake();

    $agentRole = AgentRole::factory()->create();
    $responder = EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'bug',
        'status' => 'triaged',
    ]);

    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id]);
    $bug = Bug::factory()->create([
        'status' => 'triaged',
        'assigned_agent_id' => $agent->id,
    ]);

    $event = new BugTransitioned($bug, 'new', 'triaged');
    (new DispatchAgentWork)->handle($event);

    Queue::assertPushed(RunAgentWork::class, function (RunAgentWork $job) use ($bug, $responder) {
        return $job->workItem->is($bug) && $job->responder->is($responder);
    });
});

test('dispatches for matching ops request event responder', function () {
    Queue::fake();

    $agentRole = AgentRole::factory()->create();
    $responder = EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'ops_request',
        'status' => 'planning',
    ]);

    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id]);
    $opsRequest = OpsRequest::factory()->create([
        'status' => 'planning',
        'assigned_agent_id' => $agent->id,
    ]);

    $event = new OpsRequestTransitioned($opsRequest, 'triaged', 'planning');
    (new DispatchAgentWork)->handle($event);

    Queue::assertPushed(RunAgentWork::class, function (RunAgentWork $job) use ($opsRequest, $responder) {
        return $job->workItem->is($opsRequest) && $job->responder->is($responder);
    });
});

test('team workflow falls back to agent-only when team has no workflow', function () {
    Queue::fake();

    $agentRole = AgentRole::factory()->create();
    EventResponder::factory()->forAgentRole($agentRole)->create([
        'work_item_type' => 'story',
        'status' => 'in_development',
    ]);

    $team = Team::factory()->create(['workflow_type' => 'none']);
    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id, 'team_id' => $team->id]);
    $story = Story::factory()->create([
        'status' => 'in_development',
        'assigned_agent_id' => $agent->id,
    ]);

    $event = new StoryTransitioned($story, 'design_critique', 'in_development');
    (new DispatchAgentWork)->handle($event);

    Queue::assertPushed(RunAgentWork::class, function (RunAgentWork $job) {
        return $job->team === null;
    });
});
