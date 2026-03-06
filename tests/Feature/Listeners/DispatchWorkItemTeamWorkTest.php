<?php

use App\Events\BugReported;
use App\Events\OpsRequestCreated;
use App\Events\StoryCreated;
use App\Jobs\RunTeamWork;
use App\Listeners\DispatchWorkItemTeamWork;
use App\Models\Team;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Queue;

test('dispatches RunTeamWork for teams with workflows on BugReported', function () {
    Queue::fake();

    $team = Team::factory()->chain()->create();
    $workItem = WorkItem::factory()->create();
    $workItem->project->teams()->attach($team);

    $event = new BugReported($workItem);
    app(DispatchWorkItemTeamWork::class)->handle($event);

    Queue::assertPushed(RunTeamWork::class, function (RunTeamWork $job) use ($team, $workItem) {
        return $job->team->is($team) && $job->workItem->is($workItem);
    });
});

test('dispatches RunTeamWork for teams with workflows on StoryCreated', function () {
    Queue::fake();

    $team = Team::factory()->chain()->create();
    $workItem = WorkItem::factory()->create();
    $workItem->project->teams()->attach($team);

    $event = new StoryCreated($workItem);
    app(DispatchWorkItemTeamWork::class)->handle($event);

    Queue::assertPushed(RunTeamWork::class, function (RunTeamWork $job) use ($team, $workItem) {
        return $job->team->is($team) && $job->workItem->is($workItem);
    });
});

test('dispatches RunTeamWork for teams with workflows on OpsRequestCreated', function () {
    Queue::fake();

    $team = Team::factory()->chain()->create();
    $workItem = WorkItem::factory()->create();
    $workItem->project->teams()->attach($team);

    $event = new OpsRequestCreated($workItem);
    app(DispatchWorkItemTeamWork::class)->handle($event);

    Queue::assertPushed(RunTeamWork::class, function (RunTeamWork $job) use ($team, $workItem) {
        return $job->team->is($team) && $job->workItem->is($workItem);
    });
});

test('skips teams with workflow_type none', function () {
    Queue::fake();

    $team = Team::factory()->create(['workflow_type' => 'none']);
    $workItem = WorkItem::factory()->create();
    $workItem->project->teams()->attach($team);

    $event = new BugReported($workItem);
    app(DispatchWorkItemTeamWork::class)->handle($event);

    Queue::assertNothingPushed();
});

test('dispatches to multiple teams with workflows', function () {
    Queue::fake();

    $team1 = Team::factory()->chain()->create();
    $team2 = Team::factory()->parallel()->create();
    $workItem = WorkItem::factory()->create();
    $workItem->project->teams()->attach([$team1->id, $team2->id]);

    $event = new BugReported($workItem);
    app(DispatchWorkItemTeamWork::class)->handle($event);

    Queue::assertPushed(RunTeamWork::class, 2);
});

test('does not dispatch when project has no teams', function () {
    Queue::fake();

    $workItem = WorkItem::factory()->create();

    $event = new StoryCreated($workItem);
    app(DispatchWorkItemTeamWork::class)->handle($event);

    Queue::assertNothingPushed();
});
