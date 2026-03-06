<?php

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Agent;
use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Runbook;
use App\Services\GitHubAppService;
use App\Services\OpsRequestWorkflow;
use Illuminate\Support\Facades\Http;

// --- Routing ---

test('routes deployment ops request to code_change path', function () {
    $opsRequest = OpsRequest::factory()->create(['category' => 'deployment']);
    $workflow = app(OpsRequestWorkflow::class);

    expect($workflow->route($opsRequest))->toBe('code_change');
});

test('routes infrastructure ops request to code_change path', function () {
    $opsRequest = OpsRequest::factory()->create(['category' => 'infrastructure']);
    $workflow = app(OpsRequestWorkflow::class);

    expect($workflow->route($opsRequest))->toBe('code_change');
});

test('routes config ops request to direct_action path', function () {
    $opsRequest = OpsRequest::factory()->create(['category' => 'config']);
    $workflow = app(OpsRequestWorkflow::class);

    expect($workflow->route($opsRequest))->toBe('direct_action');
});

test('routes data ops request to runbook path', function () {
    $opsRequest = OpsRequest::factory()->create(['category' => 'data']);
    $workflow = app(OpsRequestWorkflow::class);

    expect($workflow->route($opsRequest))->toBe('runbook');
});

// --- Triage ---

test('triage assigns agent and transitions to triaged', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new']);
    $agent = Agent::factory()->create();
    $workflow = app(OpsRequestWorkflow::class);

    $result = $workflow->triage($opsRequest, $agent);

    expect($result->fresh()->status)->toBe('triaged')
        ->and($result->fresh()->assigned_agent_id)->toBe($agent->id);
});

test('close as invalid from new', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new']);
    $workflow = app(OpsRequestWorkflow::class);

    $result = $workflow->closeInvalid($opsRequest);

    expect($result->fresh()->status)->toBe('closed_invalid');
});

// --- Branch Name ---

test('branch name follows convention for ops request', function () {
    $opsRequest = OpsRequest::factory()->create();
    $workflow = app(OpsRequestWorkflow::class);

    expect($workflow->branchName($opsRequest))->toBe('ops/ops-'.$opsRequest->id);
});

// --- Code Change Path - Create Branch ---

test('create branch calls GitHub API for ops request', function () {
    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id, 'default_branch' => 'main', 'url' => 'https://github.com/acme/app']);
    $opsRequest = OpsRequest::factory()->create();

    $mock = Mockery::mock(GitHubAppService::class)->makePartial();
    $mock->shouldReceive('getInstallationToken')->andReturn('test-token');
    app()->instance(GitHubAppService::class, $mock);

    Http::fake([
        'api.github.com/repos/acme/app/git/ref/heads/main' => Http::response(['object' => ['sha' => 'abc123']]),
        'api.github.com/repos/acme/app/git/refs' => Http::response(['ref' => 'refs/heads/ops/ops-'.$opsRequest->id], 201),
    ]);

    $workflow = app(OpsRequestWorkflow::class);

    $result = $workflow->createBranch($opsRequest, $repo);

    expect($result)->toBeTrue();
});

// --- Direct Action Path ---

test('low-risk direct action executes autonomously', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'low']);
    $workflow = app(OpsRequestWorkflow::class);

    $result = $workflow->executeDirectAction($opsRequest);

    expect($result->fresh()->status)->toBe('executing');
});

test('high-risk direct action triggers HITL escalation', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'high']);
    $agent = Agent::factory()->create();
    $workflow = app(OpsRequestWorkflow::class);

    $escalation = $workflow->escalateDirectAction($opsRequest, $agent);

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->work_item_id)->toBe($opsRequest->id)
        ->and($escalation->work_item_type)->toBe(OpsRequest::class)
        ->and($escalation->trigger_type)->toBe('risk')
        ->and($escalation->trigger_class)->toBe('direct_action_approval')
        ->and($escalation->resolved_at)->toBeNull();
});

test('high-risk ops request cannot execute with unresolved escalation', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'high']);
    $agent = Agent::factory()->create();
    $workflow = app(OpsRequestWorkflow::class);

    $workflow->escalateDirectAction($opsRequest, $agent);

    expect(fn () => $workflow->startExecution($opsRequest->fresh()))
        ->toThrow(InvalidStatusTransitionException::class);
});

test('high-risk ops request can execute after escalation resolved', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'high']);
    $agent = Agent::factory()->create();
    $workflow = app(OpsRequestWorkflow::class);

    $escalation = $workflow->escalateDirectAction($opsRequest, $agent);
    $escalation->update([
        'resolution' => 'Approved',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);

    $result = $workflow->startExecution($opsRequest->fresh());

    expect($result->fresh()->status)->toBe('executing');
});

// --- Runbook Path ---

test('runbook path generates runbook with steps and HITL review escalation', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning', 'category' => 'data']);
    $agent = Agent::factory()->create();
    $workflow = app(OpsRequestWorkflow::class);

    $result = $workflow->generateRunbook($opsRequest, $agent, 'Data Migration', [
        'Backup current database',
        'Run migration script',
        'Verify data integrity',
    ]);

    expect($result['runbook'])->toBeInstanceOf(Runbook::class)
        ->and($result['runbook']->title)->toBe('Data Migration')
        ->and($result['runbook']->status)->toBe('draft')
        ->and($result['runbook']->steps)->toHaveCount(3)
        ->and($result['runbook']->steps->first()->position)->toBe(1)
        ->and($result['runbook']->steps->first()->instruction)->toBe('Backup current database')
        ->and($result['runbook']->steps->last()->position)->toBe(3);

    expect($result['escalation'])->toBeInstanceOf(HitlEscalation::class)
        ->and($result['escalation']->trigger_type)->toBe('policy')
        ->and($result['escalation']->trigger_class)->toBe('runbook_review')
        ->and($result['escalation']->resolved_at)->toBeNull();
});

test('approve runbook updates status to approved', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning']);
    $runbook = Runbook::create([
        'ops_request_id' => $opsRequest->id,
        'title' => 'Test',
        'description' => 'Test',
        'status' => 'draft',
    ]);
    $workflow = app(OpsRequestWorkflow::class);

    $result = $workflow->approveRunbook($runbook);

    expect($result->fresh()->status)->toBe('approved');
});

// --- Verification ---

test('start verification transitions to verifying', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'executing']);
    $workflow = app(OpsRequestWorkflow::class);

    $result = $workflow->startVerification($opsRequest);

    expect($result->fresh()->status)->toBe('verifying');
});

test('verification success closes as done', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'verifying']);
    $workflow = app(OpsRequestWorkflow::class);

    $result = $workflow->verifySuccess($opsRequest);

    expect($result->fresh()->status)->toBe('closed_done');
});

test('verification failure escalates to HITL', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'verifying']);
    $agent = Agent::factory()->create();
    $workflow = app(OpsRequestWorkflow::class);

    $escalation = $workflow->escalateVerificationFailure(
        $opsRequest,
        $agent,
        'Unexpected state: service health check failed'
    );

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->trigger_type)->toBe('risk')
        ->and($escalation->trigger_class)->toBe('verification_failure')
        ->and($escalation->reason)->toContain('health check failed');
});

test('HITL rejection from verifying closes as rejected', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'verifying']);
    $workflow = app(OpsRequestWorkflow::class);

    $result = $workflow->rejectFromVerification($opsRequest);

    expect($result->fresh()->status)->toBe('closed_rejected');
});

// --- Cleanup ---

test('cleanup deletes branch on GitHub', function () {
    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id, 'url' => 'https://github.com/acme/app']);
    $opsRequest = OpsRequest::factory()->create(['status' => 'closed_done']);

    $mock = Mockery::mock(GitHubAppService::class)->makePartial();
    $mock->shouldReceive('getInstallationToken')->andReturn('test-token');
    app()->instance(GitHubAppService::class, $mock);

    Http::fake([
        'api.github.com/repos/acme/app/git/refs/heads/*' => Http::response(null, 204),
    ]);

    $workflow = app(OpsRequestWorkflow::class);

    $workflow->cleanup($opsRequest, [$repo]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'git/refs/heads/ops/ops-'.$opsRequest->id)
        && $request->method() === 'DELETE');
});

// --- Full Lifecycle ---

test('full direct action path: low-risk executes autonomously', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new', 'category' => 'config', 'risk_level' => 'low']);
    $agent = Agent::factory()->create();
    $workflow = app(OpsRequestWorkflow::class);

    expect($workflow->route($opsRequest))->toBe('direct_action');

    $workflow->triage($opsRequest, $agent);
    expect($opsRequest->fresh()->status)->toBe('triaged');

    $workflow->startPlanning($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('planning');

    $workflow->executeDirectAction($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('executing');

    $workflow->startVerification($opsRequest->fresh());
    $workflow->verifySuccess($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('closed_done');
});

test('full runbook path: generate -> HITL review -> approve -> execute -> verify -> close', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new', 'category' => 'data', 'risk_level' => 'high']);
    $agent = Agent::factory()->create();
    $workflow = app(OpsRequestWorkflow::class);

    expect($workflow->route($opsRequest))->toBe('runbook');

    $workflow->triage($opsRequest, $agent);
    $workflow->startPlanning($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('planning');

    $result = $workflow->generateRunbook($opsRequest->fresh(), $agent, 'Data Migration', [
        'Backup database',
        'Run migration',
        'Verify integrity',
    ]);

    expect($result['runbook']->steps)->toHaveCount(3)
        ->and($opsRequest->fresh()->hasUnresolvedEscalation())->toBeTrue();

    expect(fn () => $workflow->startExecution($opsRequest->fresh()))
        ->toThrow(InvalidStatusTransitionException::class);

    $result['escalation']->update([
        'resolution' => 'Runbook reviewed and approved',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);

    $workflow->approveRunbook($result['runbook']);
    expect($result['runbook']->fresh()->status)->toBe('approved');

    $workflow->startExecution($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('executing');

    $workflow->startVerification($opsRequest->fresh());
    $workflow->verifySuccess($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('closed_done');
});
