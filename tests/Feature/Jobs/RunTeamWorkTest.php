<?php

use App\Agents\Workflows\WorkflowResult;
use App\Agents\Workflows\WorkflowRunner;
use App\Jobs\RunTeamWork;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Team;
use App\Models\WorkItem;
use Illuminate\Support\Str;

test('runs workflow with work item content as request', function () {
    $team = Team::factory()->chain()->create();
    $workItem = WorkItem::factory()->create([
        'title' => 'Fix login bug',
        'description' => 'Users cannot log in with SSO',
        'classified_type' => 'bug',
    ]);

    $workflowRunner = Mockery::mock(WorkflowRunner::class);
    $workflowRunner->shouldReceive('run')
        ->once()
        ->withArgs(function ($t, $request, $gateway) use ($team) {
            return $t->is($team)
                && str_contains($request, 'Fix login bug')
                && str_contains($request, 'Users cannot log in with SSO')
                && str_contains($request, 'Type: bug')
                && is_callable($gateway);
        })
        ->andReturn(new WorkflowResult(response: 'done'));

    $job = new RunTeamWork($team, $workItem);
    $job->handle($workflowRunner);
});

test('includes conversation messages in request', function () {
    $team = Team::factory()->chain()->create();
    $workItem = WorkItem::factory()->create([
        'title' => 'Add dark mode',
        'classified_type' => 'story',
    ]);

    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => null,
        'title' => 'Dark mode conversation',
    ]);
    $workItem->agentConversations()->attach($conversation);

    AgentConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'user_id' => null,
        'agent' => 'anonymous',
        'role' => 'user',
        'content' => 'Please prioritize this',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    $workflowRunner = Mockery::mock(WorkflowRunner::class);
    $workflowRunner->shouldReceive('run')
        ->once()
        ->withArgs(function ($t, $request) {
            return str_contains($request, 'Add dark mode')
                && str_contains($request, '[user]: Please prioritize this');
        })
        ->andReturn(new WorkflowResult(response: 'done'));

    $job = new RunTeamWork($team, $workItem);
    $job->handle($workflowRunner);
});

test('builds request without description when null', function () {
    $team = Team::factory()->chain()->create();
    $workItem = WorkItem::factory()->create([
        'title' => 'Simple task',
        'description' => null,
        'classified_type' => 'story',
    ]);

    $workflowRunner = Mockery::mock(WorkflowRunner::class);
    $workflowRunner->shouldReceive('run')
        ->once()
        ->withArgs(function ($t, $request) {
            return str_contains($request, 'Simple task')
                && ! str_contains($request, 'Description:');
        })
        ->andReturn(new WorkflowResult(response: 'done'));

    $job = new RunTeamWork($team, $workItem);
    $job->handle($workflowRunner);
});
