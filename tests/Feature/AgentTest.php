<?php

use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Team;

test('can create an agent', function () {
    $agent = Agent::factory()->create();

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($agent->name)->not->toBeEmpty()
        ->and($agent->model)->not->toBeEmpty()
        ->and($agent->provider)->not->toBeEmpty()
        ->and($agent->confidence_threshold)->toBeFloat()
        ->and($agent->status)->toBe('idle');

    $this->assertDatabaseHas('agents', [
        'id' => $agent->id,
        'name' => $agent->name,
    ]);
});

test('agent belongs to agent role', function () {
    $agentRole = AgentRole::factory()->create();
    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id]);

    expect($agent->agentRole)->toBeInstanceOf(AgentRole::class)
        ->and($agent->agentRole->id)->toBe($agentRole->id);
});

test('agent belongs to team', function () {
    $team = Team::factory()->create();
    $agent = Agent::factory()->create(['team_id' => $team->id]);

    expect($agent->team)->toBeInstanceOf(Team::class)
        ->and($agent->team->id)->toBe($team->id);
});

test('agent role has many agents', function () {
    $agentRole = AgentRole::factory()->create();
    Agent::factory()->count(3)->create(['agent_role_id' => $agentRole->id]);

    expect($agentRole->agents)->toHaveCount(3);
});

test('team has many agents', function () {
    $team = Team::factory()->create();
    Agent::factory()->count(3)->create(['team_id' => $team->id]);

    expect($team->agents)->toHaveCount(3);
});

test('agent casts temperature to float', function () {
    $agent = Agent::factory()->create(['temperature' => 0.7]);

    expect($agent->fresh()->temperature)->toBe(0.7);
});

test('agent casts confidence_threshold to float', function () {
    $agent = Agent::factory()->create(['confidence_threshold' => 0.95]);

    expect($agent->fresh()->confidence_threshold)->toBe(0.95);
});

test('agent allows null overrides', function () {
    $agent = Agent::factory()->create([
        'temperature' => null,
        'max_steps' => null,
        'max_tokens' => null,
        'timeout' => null,
    ]);

    expect($agent->temperature)->toBeNull()
        ->and($agent->max_steps)->toBeNull()
        ->and($agent->max_tokens)->toBeNull()
        ->and($agent->timeout)->toBeNull();
});

test('can update an agent', function () {
    $agent = Agent::factory()->create();

    $agent->update([
        'name' => 'Updated Agent',
        'model' => 'claude-opus-4-6',
        'status' => 'busy',
    ]);

    expect($agent->fresh())
        ->name->toBe('Updated Agent')
        ->model->toBe('claude-opus-4-6')
        ->status->toBe('busy');
});

test('can delete an agent', function () {
    $agent = Agent::factory()->create();

    $agent->delete();

    $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
});

test('cascade deletes agents when agent role deleted', function () {
    $agentRole = AgentRole::factory()->create();
    $agent = Agent::factory()->create(['agent_role_id' => $agentRole->id]);

    $agentRole->delete();

    $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
});

test('cascade deletes agents when team deleted', function () {
    $team = Team::factory()->create();
    $agent = Agent::factory()->create(['team_id' => $team->id]);

    $team->delete();

    $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
});

test('can list agents', function () {
    Agent::factory()->count(3)->create();

    expect(Agent::count())->toBe(3);
});
