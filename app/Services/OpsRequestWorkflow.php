<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\ChangeSet;
use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use App\Models\PullRequest;
use App\Models\Runbook;
use App\Models\RunbookStep;

class OpsRequestWorkflow
{
    /**
     * Route ops request based on category: code_change, direct_action, or runbook.
     */
    public function route(OpsRequest $opsRequest): string
    {
        return match ($opsRequest->category) {
            'deployment', 'infrastructure' => 'code_change',
            'config' => 'direct_action',
            'data' => 'runbook',
            default => 'direct_action',
        };
    }

    /**
     * Triage ops request — transition to triaged.
     */
    public function triage(OpsRequest $opsRequest, Agent $agent): OpsRequest
    {
        $opsRequest->update(['assigned_agent_id' => $agent->id]);
        $opsRequest->transitionTo('triaged');

        return $opsRequest;
    }

    /**
     * Close as invalid from new status.
     */
    public function closeInvalid(OpsRequest $opsRequest): OpsRequest
    {
        $opsRequest->transitionTo('closed_invalid');

        return $opsRequest;
    }

    /**
     * Begin planning phase.
     */
    public function startPlanning(OpsRequest $opsRequest): OpsRequest
    {
        $opsRequest->transitionTo('planning');

        return $opsRequest;
    }

    // --- Code Change Path ---

    /**
     * Code change path: create a ChangeSet and follow standard PR review pipeline.
     */
    public function createChangeSet(OpsRequest $opsRequest, Agent $codingAgent, array $repos): ChangeSet
    {
        $changeSet = ChangeSet::create([
            'work_item_id' => $opsRequest->id,
            'work_item_type' => OpsRequest::class,
            'status' => 'draft',
            'summary' => 'Ops request: '.$opsRequest->title,
        ]);

        foreach ($repos as $repo) {
            $branch = Branch::create([
                'repo_id' => $repo->id,
                'name' => 'ops/ops-'.$opsRequest->id,
                'base_branch' => $repo->default_branch ?? 'main',
                'work_item_id' => $opsRequest->id,
                'work_item_type' => OpsRequest::class,
            ]);

            PullRequest::create([
                'change_set_id' => $changeSet->id,
                'branch_id' => $branch->id,
                'repo_id' => $repo->id,
                'agent_id' => $codingAgent->id,
                'title' => 'Ops #'.$opsRequest->id.': '.$opsRequest->title,
                'status' => 'open',
            ]);
        }

        return $changeSet;
    }

    // --- Direct Action Path ---

    /**
     * Execute direct action for low-risk ops requests autonomously.
     */
    public function executeDirectAction(OpsRequest $opsRequest): OpsRequest
    {
        $opsRequest->transitionTo('executing');

        return $opsRequest;
    }

    /**
     * Escalate high-risk/prod direct action for HITL approval.
     */
    public function escalateDirectAction(OpsRequest $opsRequest, Agent $agent): HitlEscalation
    {
        return HitlEscalation::create([
            'work_item_id' => $opsRequest->id,
            'work_item_type' => OpsRequest::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'risk',
            'trigger_class' => 'direct_action_approval',
            'reason' => 'High-risk or production direct action requires human approval.',
        ]);
    }

    // --- Runbook Path ---

    /**
     * Generate a Runbook with ordered steps — always goes to HITL for review.
     */
    public function generateRunbook(OpsRequest $opsRequest, Agent $agent, string $title, array $steps): array
    {
        $runbook = Runbook::create([
            'ops_request_id' => $opsRequest->id,
            'title' => $title,
            'description' => 'Generated runbook for: '.$opsRequest->title,
            'status' => 'draft',
        ]);

        foreach ($steps as $position => $instruction) {
            RunbookStep::create([
                'runbook_id' => $runbook->id,
                'position' => $position + 1,
                'instruction' => $instruction,
                'status' => 'pending',
            ]);
        }

        $escalation = HitlEscalation::create([
            'work_item_id' => $opsRequest->id,
            'work_item_type' => OpsRequest::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'policy',
            'trigger_class' => 'runbook_review',
            'reason' => 'Runbook requires human review before execution.',
        ]);

        return ['runbook' => $runbook, 'escalation' => $escalation];
    }

    /**
     * Approve runbook — update status to approved.
     */
    public function approveRunbook(Runbook $runbook): Runbook
    {
        $runbook->update(['status' => 'approved']);

        return $runbook;
    }

    // --- Execution ---

    /**
     * Start execution — transitions to executing (enforces HITL for high/critical risk).
     */
    public function startExecution(OpsRequest $opsRequest): OpsRequest
    {
        $opsRequest->transitionTo('executing');

        return $opsRequest;
    }

    // --- Verification ---

    /**
     * Start verification phase.
     */
    public function startVerification(OpsRequest $opsRequest): OpsRequest
    {
        $opsRequest->transitionTo('verifying');

        return $opsRequest;
    }

    /**
     * Verification succeeded — close as done.
     */
    public function verifySuccess(OpsRequest $opsRequest): OpsRequest
    {
        $opsRequest->transitionTo('closed_done');

        return $opsRequest;
    }

    /**
     * Verification failed or unexpected state — escalate to HITL.
     */
    public function escalateVerificationFailure(OpsRequest $opsRequest, Agent $agent, string $reason): HitlEscalation
    {
        return HitlEscalation::create([
            'work_item_id' => $opsRequest->id,
            'work_item_type' => OpsRequest::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'risk',
            'trigger_class' => 'verification_failure',
            'reason' => $reason,
        ]);
    }

    /**
     * HITL rejection from verifying — close as rejected.
     */
    public function rejectFromVerification(OpsRequest $opsRequest): OpsRequest
    {
        $opsRequest->transitionTo('closed_rejected');

        return $opsRequest;
    }

    /**
     * Cleanup worktrees after ops request completion.
     */
    public function cleanup(OpsRequest $opsRequest): void
    {
        $opsRequest->worktrees()->update([
            'status' => 'stale',
        ]);
    }
}
