<?php

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Agent;
use App\Models\Bug;
use App\Models\Dependency;
use App\Models\HitlEscalation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Story;
use App\Services\BugWorkflow;
use App\Services\GitHubAppService;
use Illuminate\Support\Facades\Http;

// --- Triage ---

test('triage agent sets severity, priority, and transitions to triaged', function () {
    $bug = Bug::factory()->create(['status' => 'new']);
    $agent = Agent::factory()->create();
    $workflow = app(BugWorkflow::class);

    $result = $workflow->triage($bug, $agent, 'critical', 0);

    expect($result->fresh()->status)->toBe('triaged')
        ->and($result->fresh()->severity)->toBe('critical')
        ->and($result->fresh()->priority)->toBe(0)
        ->and($result->fresh()->assigned_agent_id)->toBe($agent->id);
});

test('triage agent deduplicates — closes as duplicate from new', function () {
    $bug = Bug::factory()->create(['status' => 'new']);
    $workflow = app(BugWorkflow::class);

    $result = $workflow->closeDuplicate($bug);

    expect($result->fresh()->status)->toBe('closed_duplicate');
});

test('triage agent closes as cant reproduce from new', function () {
    $bug = Bug::factory()->create(['status' => 'new']);
    $workflow = app(BugWorkflow::class);

    $result = $workflow->closeCantReproduce($bug);

    expect($result->fresh()->status)->toBe('closed_cant_reproduce');
});

// --- HITL Triage Review Escalation ---

test('data loss or security bug triggers HITL triage review escalation', function () {
    $bug = Bug::factory()->create(['status' => 'new']);
    $agent = Agent::factory()->create();
    $workflow = app(BugWorkflow::class);

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
    $workflow = app(BugWorkflow::class);

    $workflow->triage($bug, $agent, 'critical', 0);

    $escalation = $workflow->escalateP0SignOff($bug, $agent);

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->trigger_type)->toBe('policy')
        ->and($escalation->trigger_class)->toBe('p0_sign_off')
        ->and($bug->hasUnresolvedEscalation())->toBeTrue();

    expect(fn () => $workflow->startFix($bug->fresh()))
        ->toThrow(InvalidStatusTransitionException::class);
});

test('P0 bug can start fix after HITL sign-off is resolved', function () {
    $bug = Bug::factory()->create(['status' => 'triaged', 'priority' => 0]);
    $agent = Agent::factory()->create();
    $workflow = app(BugWorkflow::class);

    $escalation = $workflow->escalateP0SignOff($bug, $agent);
    $escalation->update([
        'resolution' => 'Approved by engineering lead',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);

    $result = $workflow->startFix($bug->fresh());

    expect($result->fresh()->status)->toBe('in_progress');
});

// --- Branch Name ---

test('branch name follows convention for bug', function () {
    $bug = Bug::factory()->create();
    $workflow = app(BugWorkflow::class);

    expect($workflow->branchName($bug))->toBe('bugfix/bug-'.$bug->id);
});

// --- Create Branch via GitHub API ---

test('create branch calls GitHub API for bug fix branch', function () {
    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id, 'default_branch' => 'main', 'url' => 'https://github.com/acme/app']);
    $bug = Bug::factory()->create();

    $mock = Mockery::mock(GitHubAppService::class)->makePartial();
    $mock->shouldReceive('getInstallationToken')->andReturn('test-token');
    app()->instance(GitHubAppService::class, $mock);

    Http::fake([
        'api.github.com/repos/acme/app/git/ref/heads/main' => Http::response(['object' => ['sha' => 'abc123']]),
        'api.github.com/repos/acme/app/git/refs' => Http::response(['ref' => 'refs/heads/bugfix/bug-'.$bug->id], 201),
    ]);

    $workflow = app(BugWorkflow::class);

    $result = $workflow->createBranch($bug, $repo);

    expect($result)->toBeTrue();
});

// --- Review Phase ---

test('submit bug fix for review transitions to in_review', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);
    $workflow = app(BugWorkflow::class);

    $result = $workflow->submitForReview($bug);

    expect($result->fresh()->status)->toBe('in_review');
});

test('changes requested sends bug back to in_progress', function () {
    $bug = Bug::factory()->create(['status' => 'in_review']);
    $workflow = app(BugWorkflow::class);

    $result = $workflow->handleChangesRequested($bug);

    expect($result->fresh()->status)->toBe('in_progress');
});

// --- Verification and Release ---

test('verify bug fix transitions to verified', function () {
    $bug = Bug::factory()->create(['status' => 'in_review']);
    $workflow = app(BugWorkflow::class);

    $result = $workflow->verify($bug);

    expect($result->fresh()->status)->toBe('verified');
});

test('release bug fix transitions to released', function () {
    $bug = Bug::factory()->create(['status' => 'verified']);
    $workflow = app(BugWorkflow::class);

    $result = $workflow->release($bug);

    expect($result->fresh()->status)->toBe('released');
});

// --- P0 Hotfix Deploy Approval ---

test('P0 hotfix deploy triggers HITL approval escalation', function () {
    $bug = Bug::factory()->create(['status' => 'verified', 'priority' => 0, 'severity' => 'critical']);
    $agent = Agent::factory()->create();
    $workflow = app(BugWorkflow::class);

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
    $workflow = app(BugWorkflow::class);

    $result = $workflow->closeBug($bug);

    expect($result['bug']->fresh()->status)->toBe('closed_fixed')
        ->and($result['unblocked_stories'])->toHaveCount(1)
        ->and($result['unblocked_stories']->first()->id)->toBe($story->id);
});

test('closing bug with no dependent stories returns empty collection', function () {
    $bug = Bug::factory()->create(['status' => 'released']);
    $workflow = app(BugWorkflow::class);

    $result = $workflow->closeBug($bug);

    expect($result['bug']->fresh()->status)->toBe('closed_fixed')
        ->and($result['unblocked_stories'])->toHaveCount(0);
});

test('closing bug does not unblock story with other unresolved blockers', function () {
    $bug1 = Bug::factory()->create(['status' => 'released']);
    $bug2 = Bug::factory()->create(['status' => 'in_progress']);
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
    $workflow = app(BugWorkflow::class);

    $result = $workflow->closeBug($bug1);

    expect($result['bug']->fresh()->status)->toBe('closed_fixed')
        ->and($result['unblocked_stories'])->toHaveCount(0);
});

// --- Cleanup ---

test('cleanup deletes branch on GitHub after bug release', function () {
    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id, 'url' => 'https://github.com/acme/app']);
    $bug = Bug::factory()->create(['status' => 'released']);

    $mock = Mockery::mock(GitHubAppService::class)->makePartial();
    $mock->shouldReceive('getInstallationToken')->andReturn('test-token');
    app()->instance(GitHubAppService::class, $mock);

    Http::fake([
        'api.github.com/repos/acme/app/git/refs/heads/*' => Http::response(null, 204),
    ]);

    $workflow = app(BugWorkflow::class);

    $workflow->cleanup($bug, [$repo]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'git/refs/heads/bugfix/bug-'.$bug->id)
        && $request->method() === 'DELETE');
});

// --- Full Bug Lifecycle ---

test('full bug lifecycle: new -> triaged -> in_progress -> in_review -> verified -> released -> closed_fixed', function () {
    $bug = Bug::factory()->create(['status' => 'new']);
    $triageAgent = Agent::factory()->create();
    $workflow = app(BugWorkflow::class);

    $workflow->triage($bug, $triageAgent, 'major', 2);
    expect($bug->fresh()->status)->toBe('triaged');

    $workflow->startFix($bug->fresh());
    expect($bug->fresh()->status)->toBe('in_progress');

    $workflow->submitForReview($bug->fresh());
    expect($bug->fresh()->status)->toBe('in_review');

    $workflow->verify($bug->fresh());
    expect($bug->fresh()->status)->toBe('verified');

    $workflow->release($bug->fresh());
    expect($bug->fresh()->status)->toBe('released');

    $result = $workflow->closeBug($bug->fresh());
    expect($result['bug']->fresh()->status)->toBe('closed_fixed');
});

// --- Review Loop ---

test('review loop: in_review -> changes_requested -> in_progress -> in_review -> verified', function () {
    $bug = Bug::factory()->create(['status' => 'in_progress']);
    $workflow = app(BugWorkflow::class);

    $workflow->submitForReview($bug);
    expect($bug->fresh()->status)->toBe('in_review');

    $workflow->handleChangesRequested($bug->fresh());
    expect($bug->fresh()->status)->toBe('in_progress');

    $workflow->submitForReview($bug->fresh());
    expect($bug->fresh()->status)->toBe('in_review');

    $workflow->verify($bug->fresh());
    expect($bug->fresh()->status)->toBe('verified');
});
