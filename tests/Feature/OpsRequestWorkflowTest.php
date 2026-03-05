<?php

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\ChangeSet;
use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use App\Models\Repo;
use App\Models\Runbook;
use App\Models\Worktree;
use App\Services\OpsRequestWorkflow;

// --- Routing ---

test('routes deployment ops request to code_change path', function () {
    $opsRequest = OpsRequest::factory()->create(['category' => 'deployment']);
    $workflow = new OpsRequestWorkflow;

    expect($workflow->route($opsRequest))->toBe('code_change');
});

test('routes infrastructure ops request to code_change path', function () {
    $opsRequest = OpsRequest::factory()->create(['category' => 'infrastructure']);
    $workflow = new OpsRequestWorkflow;

    expect($workflow->route($opsRequest))->toBe('code_change');
});

test('routes config ops request to direct_action path', function () {
    $opsRequest = OpsRequest::factory()->create(['category' => 'config']);
    $workflow = new OpsRequestWorkflow;

    expect($workflow->route($opsRequest))->toBe('direct_action');
});

test('routes data ops request to runbook path', function () {
    $opsRequest = OpsRequest::factory()->create(['category' => 'data']);
    $workflow = new OpsRequestWorkflow;

    expect($workflow->route($opsRequest))->toBe('runbook');
});

// --- Triage ---

test('triage assigns agent and transitions to triaged', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new']);
    $agent = Agent::factory()->create();
    $workflow = new OpsRequestWorkflow;

    $result = $workflow->triage($opsRequest, $agent);

    expect($result->fresh()->status)->toBe('triaged')
        ->and($result->fresh()->assigned_agent_id)->toBe($agent->id);
});

test('close as invalid from new', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new']);
    $workflow = new OpsRequestWorkflow;

    $result = $workflow->closeInvalid($opsRequest);

    expect($result->fresh()->status)->toBe('closed_invalid');
});

// --- Code Change Path ---

test('code change path creates changeset with branches and PRs', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning', 'category' => 'deployment']);
    $agent = Agent::factory()->create();
    $repo1 = Repo::factory()->create();
    $repo2 = Repo::factory()->create();
    $workflow = new OpsRequestWorkflow;

    $changeSet = $workflow->createChangeSet($opsRequest, $agent, [$repo1, $repo2]);

    expect($changeSet)->toBeInstanceOf(ChangeSet::class)
        ->and($changeSet->work_item_id)->toBe($opsRequest->id)
        ->and($changeSet->work_item_type)->toBe(OpsRequest::class)
        ->and($changeSet->status)->toBe('draft')
        ->and($changeSet->pullRequests)->toHaveCount(2);

    $changeSet->pullRequests->each(function ($pr) use ($opsRequest) {
        expect($pr->status)->toBe('open')
            ->and($pr->title)->toContain('Ops #'.$opsRequest->id);
    });
});

// --- Direct Action Path ---

test('low-risk direct action executes autonomously', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'low']);
    $workflow = new OpsRequestWorkflow;

    $result = $workflow->executeDirectAction($opsRequest);

    expect($result->fresh()->status)->toBe('executing');
});

test('high-risk direct action triggers HITL escalation', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'high']);
    $agent = Agent::factory()->create();
    $workflow = new OpsRequestWorkflow;

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
    $workflow = new OpsRequestWorkflow;

    $workflow->escalateDirectAction($opsRequest, $agent);

    expect(fn () => $workflow->startExecution($opsRequest->fresh()))
        ->toThrow(InvalidStatusTransitionException::class);
});

test('high-risk ops request can execute after escalation resolved', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'high']);
    $agent = Agent::factory()->create();
    $workflow = new OpsRequestWorkflow;

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
    $workflow = new OpsRequestWorkflow;

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

test('runbook cannot execute until HITL review resolved', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning', 'risk_level' => 'high', 'category' => 'data']);
    $agent = Agent::factory()->create();
    $workflow = new OpsRequestWorkflow;

    $workflow->generateRunbook($opsRequest, $agent, 'Migration', ['Step 1']);

    expect(fn () => $workflow->startExecution($opsRequest->fresh()))
        ->toThrow(InvalidStatusTransitionException::class);
});

test('approve runbook updates status to approved', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'planning']);
    $runbook = Runbook::create([
        'ops_request_id' => $opsRequest->id,
        'title' => 'Test',
        'description' => 'Test',
        'status' => 'draft',
    ]);
    $workflow = new OpsRequestWorkflow;

    $result = $workflow->approveRunbook($runbook);

    expect($result->fresh()->status)->toBe('approved');
});

// --- Verification ---

test('start verification transitions to verifying', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'executing']);
    $workflow = new OpsRequestWorkflow;

    $result = $workflow->startVerification($opsRequest);

    expect($result->fresh()->status)->toBe('verifying');
});

test('verification success closes as done', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'verifying']);
    $workflow = new OpsRequestWorkflow;

    $result = $workflow->verifySuccess($opsRequest);

    expect($result->fresh()->status)->toBe('closed_done');
});

test('verification failure escalates to HITL', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'verifying']);
    $agent = Agent::factory()->create();
    $workflow = new OpsRequestWorkflow;

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
    $workflow = new OpsRequestWorkflow;

    $result = $workflow->rejectFromVerification($opsRequest);

    expect($result->fresh()->status)->toBe('closed_rejected');
});

// --- Cleanup ---

test('cleanup marks worktrees as stale', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'closed_done']);
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'path' => '/worktrees/test/ops-'.$opsRequest->id,
        'status' => 'active',
        'last_activity_at' => now(),
    ]);
    $workflow = new OpsRequestWorkflow;

    $workflow->cleanup($opsRequest);

    expect($worktree->fresh()->status)->toBe('stale');
});

// --- Full Code Change Path Lifecycle ---

test('full code change path: new -> triaged -> planning -> executing -> verifying -> closed_done', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new', 'category' => 'deployment', 'risk_level' => 'low']);
    $agent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $workflow = new OpsRequestWorkflow;

    // Route
    expect($workflow->route($opsRequest))->toBe('code_change');

    // Triage
    $workflow->triage($opsRequest, $agent);
    expect($opsRequest->fresh()->status)->toBe('triaged');

    // Planning
    $workflow->startPlanning($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('planning');

    // Create changeset
    $changeSet = $workflow->createChangeSet($opsRequest->fresh(), $agent, [$repo]);
    expect($changeSet->pullRequests)->toHaveCount(1);

    // Execute
    $workflow->startExecution($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('executing');

    // Verify
    $workflow->startVerification($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('verifying');

    // Close
    $workflow->verifySuccess($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('closed_done');

    // Cleanup
    $workflow->cleanup($opsRequest->fresh());
});

// --- Full Direct Action Path Lifecycle ---

test('full direct action path: low-risk executes autonomously', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new', 'category' => 'config', 'risk_level' => 'low']);
    $agent = Agent::factory()->create();
    $workflow = new OpsRequestWorkflow;

    // Route
    expect($workflow->route($opsRequest))->toBe('direct_action');

    // Triage
    $workflow->triage($opsRequest, $agent);
    expect($opsRequest->fresh()->status)->toBe('triaged');

    // Planning
    $workflow->startPlanning($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('planning');

    // Execute directly (low risk)
    $workflow->executeDirectAction($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('executing');

    // Verify and close
    $workflow->startVerification($opsRequest->fresh());
    $workflow->verifySuccess($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('closed_done');
});

test('full direct action path: high-risk requires HITL approval', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new', 'category' => 'config', 'risk_level' => 'high']);
    $agent = Agent::factory()->create();
    $workflow = new OpsRequestWorkflow;

    // Triage and plan
    $workflow->triage($opsRequest, $agent);
    $workflow->startPlanning($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('planning');

    // Escalate for HITL
    $escalation = $workflow->escalateDirectAction($opsRequest->fresh(), $agent);

    // Cannot execute with unresolved escalation
    expect(fn () => $workflow->startExecution($opsRequest->fresh()))
        ->toThrow(InvalidStatusTransitionException::class);

    // Resolve escalation
    $escalation->update([
        'resolution' => 'Approved for production',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);

    // Now can execute
    $workflow->startExecution($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('executing');

    // Verify and close
    $workflow->startVerification($opsRequest->fresh());
    $workflow->verifySuccess($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('closed_done');
});

// --- Full Runbook Path Lifecycle ---

test('full runbook path: generate -> HITL review -> approve -> execute -> verify -> close', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new', 'category' => 'data', 'risk_level' => 'high']);
    $agent = Agent::factory()->create();
    $workflow = new OpsRequestWorkflow;

    // Route
    expect($workflow->route($opsRequest))->toBe('runbook');

    // Triage and plan
    $workflow->triage($opsRequest, $agent);
    $workflow->startPlanning($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('planning');

    // Generate runbook (always escalates to HITL)
    $result = $workflow->generateRunbook($opsRequest->fresh(), $agent, 'Data Migration', [
        'Backup database',
        'Run migration',
        'Verify integrity',
    ]);

    expect($result['runbook']->steps)->toHaveCount(3)
        ->and($opsRequest->fresh()->hasUnresolvedEscalation())->toBeTrue();

    // Cannot execute until HITL resolves
    expect(fn () => $workflow->startExecution($opsRequest->fresh()))
        ->toThrow(InvalidStatusTransitionException::class);

    // Resolve HITL review
    $result['escalation']->update([
        'resolution' => 'Runbook reviewed and approved',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);

    // Approve runbook
    $workflow->approveRunbook($result['runbook']);
    expect($result['runbook']->fresh()->status)->toBe('approved');

    // Execute
    $workflow->startExecution($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('executing');

    // Verify and close
    $workflow->startVerification($opsRequest->fresh());
    $workflow->verifySuccess($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('closed_done');
});

// --- Verification Failure with HITL Rejection ---

test('full lifecycle with verification failure and HITL rejection', function () {
    $opsRequest = OpsRequest::factory()->create(['status' => 'new', 'category' => 'config', 'risk_level' => 'low']);
    $agent = Agent::factory()->create();
    $workflow = new OpsRequestWorkflow;

    // Triage -> Plan -> Execute
    $workflow->triage($opsRequest, $agent);
    $workflow->startPlanning($opsRequest->fresh());
    $workflow->executeDirectAction($opsRequest->fresh());
    $workflow->startVerification($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('verifying');

    // Verification fails — escalate
    $escalation = $workflow->escalateVerificationFailure(
        $opsRequest->fresh(),
        $agent,
        'Service returned unexpected 503 errors'
    );
    expect($escalation->trigger_class)->toBe('verification_failure');

    // HITL rejects the outcome
    $workflow->rejectFromVerification($opsRequest->fresh());
    expect($opsRequest->fresh()->status)->toBe('closed_rejected');
});
