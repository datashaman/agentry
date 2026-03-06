<?php

use App\Models\Agent;
use App\Models\OpsRequest;
use App\Models\Project;

test('can create an ops request', function () {
    $opsRequest = OpsRequest::factory()->create();

    expect($opsRequest)->toBeInstanceOf(OpsRequest::class)
        ->and($opsRequest->title)->not->toBeEmpty()
        ->and($opsRequest->status)->toBe('new');

    $this->assertDatabaseHas('ops_requests', [
        'id' => $opsRequest->id,
        'title' => $opsRequest->title,
    ]);
});

test('ops request belongs to project', function () {
    $project = Project::factory()->create();
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    expect($opsRequest->project)->toBeInstanceOf(Project::class)
        ->and($opsRequest->project->id)->toBe($project->id);
});

test('project has many ops requests', function () {
    $project = Project::factory()->create();
    OpsRequest::factory()->count(3)->create(['project_id' => $project->id]);

    expect($project->opsRequests)->toHaveCount(3);
});

test('ops request optionally assignable to agent', function () {
    $agent = Agent::factory()->create();

    $without = OpsRequest::factory()->create(['assigned_agent_id' => null]);
    $with = OpsRequest::factory()->create(['assigned_agent_id' => $agent->id]);

    expect($without->assignedAgent)->toBeNull()
        ->and($with->assignedAgent)->toBeInstanceOf(Agent::class)
        ->and($with->assignedAgent->id)->toBe($agent->id);
});

test('agent has many assigned ops requests', function () {
    $agent = Agent::factory()->create();
    OpsRequest::factory()->count(2)->create(['assigned_agent_id' => $agent->id]);

    expect($agent->assignedOpsRequests)->toHaveCount(2);
});

test('ops request title is required', function () {
    expect(fn () => OpsRequest::factory()->create(['title' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('ops request description is nullable', function () {
    $opsRequest = OpsRequest::factory()->create(['description' => null]);

    expect($opsRequest->description)->toBeNull();
});

test('ops request status defaults to new', function () {
    $opsRequest = OpsRequest::factory()->create();

    expect($opsRequest->fresh()->status)->toBe('new');
});

test('ops request status supports all states', function () {
    $statuses = [
        'new', 'triaged', 'planning', 'executing', 'verifying',
        'closed_done', 'closed_invalid', 'closed_rejected',
    ];

    foreach ($statuses as $status) {
        $opsRequest = OpsRequest::factory()->create(['status' => $status]);
        expect($opsRequest->fresh()->status)->toBe($status);
    }
});

test('ops request category supports all types', function () {
    $categories = ['deployment', 'infrastructure', 'config', 'data'];

    foreach ($categories as $category) {
        $opsRequest = OpsRequest::factory()->create(['category' => $category]);
        expect($opsRequest->fresh()->category)->toBe($category);
    }
});

test('ops request execution_type supports all types', function () {
    $types = ['automated', 'supervised', 'manual'];

    foreach ($types as $type) {
        $opsRequest = OpsRequest::factory()->create(['execution_type' => $type]);
        expect($opsRequest->fresh()->execution_type)->toBe($type);
    }
});

test('ops request risk_level supports all levels', function () {
    $levels = ['low', 'medium', 'high', 'critical'];

    foreach ($levels as $level) {
        $opsRequest = OpsRequest::factory()->create(['risk_level' => $level]);
        expect($opsRequest->fresh()->risk_level)->toBe($level);
    }
});

test('ops request scheduled_at is cast to datetime', function () {
    $opsRequest = OpsRequest::factory()->create([
        'scheduled_at' => '2026-06-15 10:00:00',
    ]);

    expect($opsRequest->fresh()->scheduled_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

test('ops request environment is nullable', function () {
    $opsRequest = OpsRequest::factory()->create(['environment' => null]);

    expect($opsRequest->environment)->toBeNull();
});

test('can update an ops request', function () {
    $opsRequest = OpsRequest::factory()->create();

    $opsRequest->update([
        'title' => 'Updated Ops Request',
        'status' => 'triaged',
        'risk_level' => 'critical',
    ]);

    expect($opsRequest->fresh())
        ->title->toBe('Updated Ops Request')
        ->status->toBe('triaged')
        ->risk_level->toBe('critical');
});

test('can delete an ops request', function () {
    $opsRequest = OpsRequest::factory()->create();

    $opsRequest->delete();

    $this->assertDatabaseMissing('ops_requests', ['id' => $opsRequest->id]);
});

test('cascade deletes ops requests when project deleted', function () {
    $project = Project::factory()->create();
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    $project->delete();

    $this->assertDatabaseMissing('ops_requests', ['id' => $opsRequest->id]);
});

test('agent deletion nullifies ops request assigned_agent_id', function () {
    $agent = Agent::factory()->create();
    $opsRequest = OpsRequest::factory()->create(['assigned_agent_id' => $agent->id]);

    $agent->delete();

    expect($opsRequest->fresh()->assigned_agent_id)->toBeNull();
});

test('can list ops requests', function () {
    OpsRequest::factory()->count(3)->create();

    expect(OpsRequest::count())->toBe(3);
});
