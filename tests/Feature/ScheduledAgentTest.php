<?php

use App\Console\Commands\RunScheduledAgents;
use App\Jobs\RunScheduledAgentWork;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Support\Facades\Queue;

test('scheduled command dispatches jobs for due agents', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->forOrganization($organization)->create();

    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
        'schedule' => 'every_5_minutes',
        'scheduled_instructions' => 'Check system health.',
        'last_scheduled_at' => null,
    ]);

    $this->artisan('agents:run-scheduled')
        ->assertSuccessful();

    Queue::assertPushed(RunScheduledAgentWork::class, function ($job) use ($agent) {
        return $job->agent->id === $agent->id;
    });
});

test('scheduled command skips agents that are not yet due', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->forOrganization($organization)->create();

    Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
        'schedule' => 'every_5_minutes',
        'scheduled_instructions' => 'Check system health.',
        'last_scheduled_at' => now(),
    ]);

    $this->artisan('agents:run-scheduled')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('scheduled command dispatches when enough time has passed', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->forOrganization($organization)->create();

    Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
        'schedule' => 'every_5_minutes',
        'scheduled_instructions' => 'Check system health.',
        'last_scheduled_at' => now()->subMinutes(6),
    ]);

    $this->artisan('agents:run-scheduled')
        ->assertSuccessful();

    Queue::assertPushed(RunScheduledAgentWork::class);
});

test('scheduled command skips agents without scheduled instructions', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->forOrganization($organization)->create();

    Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
        'schedule' => 'every_5_minutes',
        'scheduled_instructions' => null,
    ]);

    $this->artisan('agents:run-scheduled')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('scheduled command skips agents with unknown schedule preset', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->forOrganization($organization)->create();

    Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
        'schedule' => 'invalid_schedule',
        'scheduled_instructions' => 'Do something.',
        'last_scheduled_at' => null,
    ]);

    $this->artisan('agents:run-scheduled')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('RunScheduledAgentWork job updates last_scheduled_at', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->forOrganization($organization)->create([
        'instructions' => 'You are a monitor.',
    ]);

    $agent = Agent::factory()->create([
        'agent_role_id' => $agentRole->id,
        'team_id' => $team->id,
        'schedule' => 'every_5_minutes',
        'scheduled_instructions' => 'Check logs.',
        'last_scheduled_at' => null,
    ]);

    (new RunScheduledAgentWork($agent))->handle(app(\App\Agents\AgentResolver::class));

    $agent->refresh();
    expect($agent->last_scheduled_at)->not->toBeNull();
});

test('schedule presets map to expected minute intervals', function () {
    expect(RunScheduledAgents::SCHEDULES)->toBe([
        'every_minute' => 1,
        'every_5_minutes' => 5,
        'every_15_minutes' => 15,
        'every_30_minutes' => 30,
        'hourly' => 60,
        'daily' => 1440,
    ]);
});
