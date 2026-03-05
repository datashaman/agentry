<?php

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Team;

test('can create an agent', function () {
    $agent = Agent::factory()->create();

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($agent->name)->not->toBeEmpty()
        ->and($agent->model)->not->toBeEmpty()
        ->and($agent->confidence_threshold)->toBeFloat()
        ->and($agent->tools)->toBeArray()
        ->and($agent->capabilities)->toBeArray()
        ->and($agent->status)->toBe('idle');

    $this->assertDatabaseHas('agents', [
        'id' => $agent->id,
        'name' => $agent->name,
    ]);
});

test('agent belongs to agent type', function () {
    $agentType = AgentType::factory()->create();
    $agent = Agent::factory()->create(['agent_type_id' => $agentType->id]);

    expect($agent->agentType)->toBeInstanceOf(AgentType::class)
        ->and($agent->agentType->id)->toBe($agentType->id);
});

test('agent belongs to team', function () {
    $team = Team::factory()->create();
    $agent = Agent::factory()->create(['team_id' => $team->id]);

    expect($agent->team)->toBeInstanceOf(Team::class)
        ->and($agent->team->id)->toBe($team->id);
});

test('agent type has many agents', function () {
    $agentType = AgentType::factory()->create();
    Agent::factory()->count(3)->create(['agent_type_id' => $agentType->id]);

    expect($agentType->agents)->toHaveCount(3);
});

test('team has many agents', function () {
    $team = Team::factory()->create();
    Agent::factory()->count(3)->create(['team_id' => $team->id]);

    expect($team->agents)->toHaveCount(3);
});

test('agent casts tools and capabilities to arrays', function () {
    $tools = ['code_editor', 'terminal'];
    $capabilities = ['write_code', 'run_tests'];

    $agent = Agent::factory()->create([
        'tools' => $tools,
        'capabilities' => $capabilities,
    ]);

    $fresh = $agent->fresh();

    expect($fresh->tools)->toBe($tools)
        ->and($fresh->capabilities)->toBe($capabilities);
});

test('agent casts confidence_threshold to float', function () {
    $agent = Agent::factory()->create(['confidence_threshold' => 0.95]);

    expect($agent->fresh()->confidence_threshold)->toBe(0.95);
});

test('agent allows null tools and capabilities', function () {
    $agent = Agent::factory()->create([
        'tools' => null,
        'capabilities' => null,
    ]);

    expect($agent->tools)->toBeNull()
        ->and($agent->capabilities)->toBeNull();
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

test('cascade deletes agents when agent type deleted', function () {
    $agentType = AgentType::factory()->create();
    $agent = Agent::factory()->create(['agent_type_id' => $agentType->id]);

    $agentType->delete();

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
