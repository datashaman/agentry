<?php

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Bug;
use App\Models\ChangeSet;
use App\Models\Dependency;
use App\Models\HitlEscalation;
use App\Models\Repo;
use App\Models\Story;
use App\Models\Worktree;
use App\Services\BugWorkflow;

// --- Triage ---

test('triage agent sets severity, priority, and transitions to triaged', function () {
    $bug = Bug::factory()->create(['status' => 'new']);
    $agent = Agent::factory()->create();
    $workflow = new BugWorkflow;

    $result = $workflow->triage($bug, $agent, 'critical', 0);

    expect($result->fresh()->status)->toBe('triaged')
        ->and($result->fresh()->severity)->toBe('critical')
        ->and($result->fresh()->priority)->toBe(0)
        ->and($result->fresh()->assigned_agent_id)->toBe($agent->id);
});

test('triage agent deduplicates — closes as duplicate from new', function () {
    $bug = Bug::factory()->create(['status' => 'new']);
    $workflow = new BugWorkflow;

    $result = $workflow->closeDuplicate($bug);

    expect($result->fresh()->status)->toBe('closed_duplicate');
});

test('triage agent closes as cant reproduce from new', function () {
    $bug = Bug::factory()->create(['status' => 'new']);
    $workflow = new BugWorkflow;

    $result = $workflow->closeCantReproduce($bug);

    expect($result->fresh()->status)->toBe('closed_cant_reproduce');
});

// --- HITL Triage Review Escalation ---

test('data loss or security bug triggers HITL triage review escalation', function () {
    $bug = Bug::factory()->create(['status' => 'new']);
    $agent = Agent::factory()->create();
    $workflow = new BugWorkflow;

    $escalation = $workflow->escalateTriageReview($bug, $agent, 'Potential data loss detected in user records');

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->work_item_id)->toBe($bug->id)
        ->and($escalation->work_item_type)->toBe(Bug::class)
        ->and($escalation->raised_by_agent_id)->toBe($agent->id)
        ->and($escalation->trigger_type)->toBe('risk')
        ->and($escalation->trigger_class)->toBe('triage_review')
        ->and($escalation->reason)->toContain('data loss')
        ->and($escalation->resolved_at)->toBeNull();
});

// --- P0 Sign-Off ---

test('P0 bug requires HITL sign-off before work begins', function () {
    $bug = Bug::factory()->create(['status' => 'new', 'priority' => 0]);
    $agent = Agent::factory()->create();
    $workflow = new BugWorkflow;

    // Triage the bug
    $workflow->triage($bug, $agent, 'critical', 0);

    // Escalate for P0 sign-off
    $escalation = $workflow->escalateP0SignOff($bug, $agent);

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->trigger_type)->toBe('policy')
        ->and($escalation->trigger_class)->toBe('p0_sign_off')
        ->and($bug->hasUnresolvedEscalation())->toBeTrue();

    // Cannot start fix while escalation is unresolved
    expect(fn () => $workflow->startFix($bug->fresh()))
        ->toThrow(InvalidStatusTransitionException::class);
});

test('P0 bug can start fix after HITL sign-off is resolved', function () {
    $bug = Bug::factory()->create(['status' => 'triaged', 'priority' => 0]);
    $agent = Agent::factory()->create();
    $workflow = new BugWorkflow;

    // Escalate and resolve
    $escalation = $workflow->escalateP0SignOff($bug, $agent);
    $escalation->update([
        'resolution' => 'Approved by engineering lead',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);

    // Now can start fix
    $result = $workflow->startFix($bug->fresh());

    expect($result->fresh()->status)->toBe('in_progress');
});

// --- Fix Phase - Worktree/ChangeSet/PR Pattern ---

test('check worktree returns create when no active worktree exists', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);
    $repo = Repo::factory()->create();
    $workflow = new BugWorkflow;

    $result = $workflow->checkWorktree($bug, $repo);

    expect($result['action'])->toBe('create')
        ->and($result['worktree'])->toBeNull();
});

test('check worktree returns resume when same bug worktree exists', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'path' => '/worktrees/test/bug-'.$bug->id,
        'status' => 'active',
        'last_activity_at' => now(),
    ]);
    $workflow = new BugWorkflow;

    $result = $workflow->checkWorktree($bug, $repo);

    expect($result['action'])->toBe('resume')
        ->and($result['worktree']->id)->toBe($worktree->id);
});

test('check worktree returns escalate when different work item worktree exists', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);
    $otherBug = Bug::factory()->create();
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $otherBug->id,
        'work_item_type' => Bug::class,
        'path' => '/worktrees/test/other',
        'status' => 'active',
        'last_activity_at' => now(),
    ]);
    $workflow = new BugWorkflow;

    $result = $workflow->checkWorktree($bug, $repo);

    expect($result['action'])->toBe('escalate');
});

test('create worktree for bug fix', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $workflow = new BugWorkflow;

    $worktree = $workflow->createWorktree($bug, $repo, $branch);

    expect($worktree)->toBeInstanceOf(Worktree::class)
        ->and($worktree->work_item_id)->toBe($bug->id)
        ->and($worktree->work_item_type)->toBe(Bug::class)
        ->and($worktree->status)->toBe('active')
        ->and($worktree->path)->toContain('bug-'.$bug->id);
});

test('create change set with branches and PRs for bug fix', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);
    $agent = Agent::factory()->create();
    $repo1 = Repo::factory()->create();
    $repo2 = Repo::factory()->create();
    $workflow = new BugWorkflow;

    $changeSet = $workflow->createChangeSet($bug, $agent, [$repo1, $repo2]);

    expect($changeSet)->toBeInstanceOf(ChangeSet::class)
        ->and($changeSet->work_item_id)->toBe($bug->id)
        ->and($changeSet->work_item_type)->toBe(Bug::class)
        ->and($changeSet->status)->toBe('draft')
        ->and($changeSet->pullRequests)->toHaveCount(2);

    $changeSet->pullRequests->each(function ($pr) use ($bug) {
        expect($pr->status)->toBe('open')
            ->and($pr->title)->toContain('Bug #'.$bug->id);
    });
});

// --- Review Phase ---

test('submit bug fix for review transitions to in_review', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);
    $workflow = new BugWorkflow;

    $result = $workflow->submitForReview($bug);

    expect($result->fresh()->status)->toBe('in_review');
});

test('changes requested sends bug back to in_progress', function () {
    $bug = Bug::factory()->create(['status' => 'in_review']);
    $workflow = new BugWorkflow;

    $result = $workflow->handleChangesRequested($bug);

    expect($result->fresh()->status)->toBe('in_progress');
});

// --- Verification and Release ---

test('verify bug fix transitions to verified', function () {
    $bug = Bug::factory()->create(['status' => 'in_review']);
    $workflow = new BugWorkflow;

    $result = $workflow->verify($bug);

    expect($result->fresh()->status)->toBe('verified');
});

test('release bug fix transitions to released', function () {
    $bug = Bug::factory()->create(['status' => 'verified']);
    $workflow = new BugWorkflow;

    $result = $workflow->release($bug);

    expect($result->fresh()->status)->toBe('released');
});

// --- P0 Hotfix Deploy Approval ---

test('P0 hotfix deploy triggers HITL approval escalation', function () {
    $bug = Bug::factory()->create(['status' => 'verified', 'priority' => 0, 'severity' => 'critical']);
    $agent = Agent::factory()->create();
    $workflow = new BugWorkflow;

    $escalation = $workflow->escalateHotfixApproval($bug, $agent);

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->work_item_id)->toBe($bug->id)
        ->and($escalation->work_item_type)->toBe(Bug::class)
        ->and($escalation->trigger_type)->toBe('risk')
        ->and($escalation->trigger_class)->toBe('hotfix_approval')
        ->and($escalation->resolved_at)->toBeNull();
});

// --- Bug Closure and Unblocking Stories ---

test('closing bug checks if fix unblocks dependent stories', function () {
    $bug = Bug::factory()->create(['status' => 'released']);
    $story = Story::factory()->create(['status' => 'blocked']);
    Dependency::create([
        'blocker_type' => Bug::class,
        'blocker_id' => $bug->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);
    $workflow = new BugWorkflow;

    $result = $workflow->closeBug($bug);

    expect($result['bug']->fresh()->status)->toBe('closed_fixed')
        ->and($result['unblocked_stories'])->toHaveCount(1)
        ->and($result['unblocked_stories']->first()->id)->toBe($story->id);
});

test('closing bug with no dependent stories returns empty collection', function () {
    $bug = Bug::factory()->create(['status' => 'released']);
    $workflow = new BugWorkflow;

    $result = $workflow->closeBug($bug);

    expect($result['bug']->fresh()->status)->toBe('closed_fixed')
        ->and($result['unblocked_stories'])->toHaveCount(0);
});

test('closing bug does not unblock story with other unresolved blockers', function () {
    $bug1 = Bug::factory()->create(['status' => 'released']);
    $bug2 = Bug::factory()->create(['status' => 'in_progress']); // Still unresolved
    $story = Story::factory()->create(['status' => 'blocked']);
    Dependency::create([
        'blocker_type' => Bug::class,
        'blocker_id' => $bug1->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);
    Dependency::create([
        'blocker_type' => Bug::class,
        'blocker_id' => $bug2->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);
    $workflow = new BugWorkflow;

    $result = $workflow->closeBug($bug1);

    expect($result['bug']->fresh()->status)->toBe('closed_fixed')
        ->and($result['unblocked_stories'])->toHaveCount(0);
});

// --- Cleanup ---

test('cleanup marks worktrees as stale after bug release', function () {
    $bug = Bug::factory()->create(['status' => 'released']);
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'path' => '/worktrees/test/bug-'.$bug->id,
        'status' => 'active',
        'last_activity_at' => now(),
    ]);
    $workflow = new BugWorkflow;

    $workflow->cleanup($bug);

    expect($worktree->fresh()->status)->toBe('stale');
});

// --- Full Bug Lifecycle ---

test('full bug lifecycle: new -> triaged -> in_progress -> in_review -> verified -> released -> closed_fixed', function () {
    $bug = Bug::factory()->create(['status' => 'new']);
    $triageAgent = Agent::factory()->create();
    $codingAgent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $workflow = new BugWorkflow;

    // Step 1: Triage
    $workflow->triage($bug, $triageAgent, 'major', 2);
    expect($bug->fresh()->status)->toBe('triaged');

    // Step 2: Start fix
    $workflow->startFix($bug->fresh());
    expect($bug->fresh()->status)->toBe('in_progress');

    // Step 3: Create changeset
    $changeSet = $workflow->createChangeSet($bug->fresh(), $codingAgent, [$repo]);
    expect($changeSet->pullRequests)->toHaveCount(1);

    // Step 4: Submit for review
    $workflow->submitForReview($bug->fresh());
    expect($bug->fresh()->status)->toBe('in_review');

    // Step 5: Verify
    $workflow->verify($bug->fresh());
    expect($bug->fresh()->status)->toBe('verified');

    // Step 6: Release
    $workflow->release($bug->fresh());
    expect($bug->fresh()->status)->toBe('released');

    // Step 7: Close
    $result = $workflow->closeBug($bug->fresh());
    expect($result['bug']->fresh()->status)->toBe('closed_fixed');

    // Step 8: Cleanup
    $workflow->cleanup($bug->fresh());
});

// --- Full P0 Bug Lifecycle ---

test('full P0 lifecycle: triage -> HITL sign-off -> fix -> HITL hotfix approval -> release -> close with unblock', function () {
    $bug = Bug::factory()->create(['status' => 'new', 'priority' => 0]);
    $story = Story::factory()->create(['status' => 'blocked']);
    Dependency::create([
        'blocker_type' => Bug::class,
        'blocker_id' => $bug->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);
    $triageAgent = Agent::factory()->create();
    $codingAgent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $workflow = new BugWorkflow;

    // Step 1: Triage as P0
    $workflow->triage($bug, $triageAgent, 'critical', 0);
    expect($bug->fresh()->status)->toBe('triaged');

    // Step 2: P0 requires HITL sign-off
    $signOff = $workflow->escalateP0SignOff($bug->fresh(), $triageAgent);
    expect($bug->fresh()->hasUnresolvedEscalation())->toBeTrue();

    // Cannot start fix without sign-off
    expect(fn () => $workflow->startFix($bug->fresh()))
        ->toThrow(InvalidStatusTransitionException::class);

    // Resolve sign-off
    $signOff->update([
        'resolution' => 'Approved',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);

    // Step 3: Start fix
    $workflow->startFix($bug->fresh());
    expect($bug->fresh()->status)->toBe('in_progress');

    // Step 4: Create changeset and submit for review
    $workflow->createChangeSet($bug->fresh(), $codingAgent, [$repo]);
    $workflow->submitForReview($bug->fresh());
    expect($bug->fresh()->status)->toBe('in_review');

    // Step 5: Verify
    $workflow->verify($bug->fresh());
    expect($bug->fresh()->status)->toBe('verified');

    // Step 6: P0 hotfix deploy requires HITL approval
    $hotfixApproval = $workflow->escalateHotfixApproval($bug->fresh(), $codingAgent);
    expect($hotfixApproval->trigger_class)->toBe('hotfix_approval');

    // Resolve hotfix approval
    $hotfixApproval->update([
        'resolution' => 'Hotfix approved for production',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);

    // Step 7: Release
    $workflow->release($bug->fresh());
    expect($bug->fresh()->status)->toBe('released');

    // Step 8: Close and check unblocking
    $result = $workflow->closeBug($bug->fresh());
    expect($result['bug']->fresh()->status)->toBe('closed_fixed')
        ->and($result['unblocked_stories'])->toHaveCount(1)
        ->and($result['unblocked_stories']->first()->id)->toBe($story->id);
});

// --- Review Loop ---

test('review loop: in_review -> changes_requested -> in_progress -> in_review -> verified', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);
    $workflow = new BugWorkflow;

    // Submit for review
    $workflow->submitForReview($bug);
    expect($bug->fresh()->status)->toBe('in_review');

    // Changes requested
    $workflow->handleChangesRequested($bug->fresh());
    expect($bug->fresh()->status)->toBe('in_progress');

    // Re-submit
    $workflow->submitForReview($bug->fresh());
    expect($bug->fresh()->status)->toBe('in_review');

    // Verify
    $workflow->verify($bug->fresh());
    expect($bug->fresh()->status)->toBe('verified');
});
