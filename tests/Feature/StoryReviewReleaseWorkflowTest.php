<?php

use App\Models\Agent;
use App\Models\Branch;
use App\Models\Bug;
use App\Models\ChangeSet;
use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\PullRequest;
use App\Models\Repo;
use App\Models\Review;
use App\Models\Story;
use App\Models\Worktree;
use App\Services\StoryReviewReleaseWorkflow;

// --- Review Agent Reviews PR ---

test('review agent creates a review for a pull request', function () {
    $pr = PullRequest::factory()->create(['status' => 'open']);
    $reviewAgent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    $review = $workflow->reviewPullRequest($pr, $reviewAgent, 'approved', 'Looks good');

    expect($review)->toBeInstanceOf(Review::class)
        ->and($review->pull_request_id)->toBe($pr->id)
        ->and($review->agent_id)->toBe($reviewAgent->id)
        ->and($review->status)->toBe('approved')
        ->and($review->body)->toBe('Looks good')
        ->and($review->submitted_at)->not->toBeNull();
});

test('review agent can request changes on a pull request', function () {
    $pr = PullRequest::factory()->create(['status' => 'open']);
    $reviewAgent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    $review = $workflow->reviewPullRequest($pr, $reviewAgent, 'changes_requested', 'Needs refactoring');

    expect($review->status)->toBe('changes_requested')
        ->and($review->body)->toBe('Needs refactoring');
});

test('review per repo creates independent reviews for each PR', function () {
    $repo1 = Repo::factory()->create();
    $repo2 = Repo::factory()->create();
    $story = Story::factory()->create(['status' => 'in_review']);
    $changeSet = ChangeSet::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'status' => 'draft',
        'summary' => 'Test changes',
    ]);
    $branch1 = Branch::factory()->create(['repo_id' => $repo1->id]);
    $branch2 = Branch::factory()->create(['repo_id' => $repo2->id]);
    $pr1 = PullRequest::create([
        'change_set_id' => $changeSet->id,
        'branch_id' => $branch1->id,
        'repo_id' => $repo1->id,
        'agent_id' => Agent::factory()->create()->id,
        'title' => 'PR 1',
        'status' => 'open',
    ]);
    $pr2 = PullRequest::create([
        'change_set_id' => $changeSet->id,
        'branch_id' => $branch2->id,
        'repo_id' => $repo2->id,
        'agent_id' => Agent::factory()->create()->id,
        'title' => 'PR 2',
        'status' => 'open',
    ]);
    $reviewAgent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    $review1 = $workflow->reviewPullRequest($pr1, $reviewAgent, 'approved');
    $review2 = $workflow->reviewPullRequest($pr2, $reviewAgent, 'approved');

    expect($review1->pull_request_id)->toBe($pr1->id)
        ->and($review2->pull_request_id)->toBe($pr2->id)
        ->and($review1->id)->not->toBe($review2->id);
});

// --- Submit for Review ---

test('submit for review transitions story from in_development to in_review', function () {
    $story = Story::factory()->create(['status' => 'in_development']);
    $workflow = new StoryReviewReleaseWorkflow;

    $result = $workflow->submitForReview($story);

    expect($result->fresh()->status)->toBe('in_review');
});

// --- Changes Needed - Return to Development ---

test('changes requested triggers design critic re-run and returns to development', function () {
    $story = Story::factory()->create(['status' => 'in_review', 'dev_iteration_count' => 1]);
    $designCriticAgent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    $critique = $workflow->handleChangesRequested($story, $designCriticAgent);

    expect($story->fresh()->status)->toBe('in_development')
        ->and($critique)->toBeInstanceOf(Critique::class)
        ->and($critique->critic_type)->toBe('design')
        ->and($critique->work_item_id)->toBe($story->id)
        ->and($critique->work_item_type)->toBe(Story::class)
        ->and($critique->agent_id)->toBe($designCriticAgent->id)
        ->and($critique->disposition)->toBe('pending');
});

// --- Security / Breaking API HITL Escalation ---

test('security surface triggers HITL code review escalation', function () {
    $story = Story::factory()->create(['status' => 'in_review']);
    $agent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    $escalation = $workflow->escalateCodeReview($story, $agent, 'Security-sensitive authentication changes detected');

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->work_item_id)->toBe($story->id)
        ->and($escalation->work_item_type)->toBe(Story::class)
        ->and($escalation->trigger_type)->toBe('risk')
        ->and($escalation->trigger_class)->toBe('code_review')
        ->and($escalation->reason)->toBe('Security-sensitive authentication changes detected')
        ->and($escalation->resolved_at)->toBeNull();
});

test('breaking API change triggers HITL code review escalation', function () {
    $story = Story::factory()->create(['status' => 'in_review']);
    $agent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    $escalation = $workflow->escalateCodeReview($story, $agent, 'Breaking API change: removed endpoint /api/v1/users');

    expect($escalation->trigger_type)->toBe('risk')
        ->and($escalation->trigger_class)->toBe('code_review')
        ->and($escalation->raised_by_agent_id)->toBe($agent->id);
});

// --- QA Pass ---

test('pass QA transitions story from in_review to staging', function () {
    $story = Story::factory()->create(['status' => 'in_review']);
    $workflow = new StoryReviewReleaseWorkflow;

    $result = $workflow->passQa($story);

    expect($result->fresh()->status)->toBe('staging');
});

// --- QA Failure - Bug Creation ---

test('QA regression files a bug linked to the originating story', function () {
    $story = Story::factory()->create(['status' => 'in_review']);
    $testAgent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    $bug = $workflow->failQaWithRegression(
        $story,
        $testAgent,
        'Login form regression',
        'The login form no longer validates email format after story changes',
    );

    expect($bug)->toBeInstanceOf(Bug::class)
        ->and($bug->linked_story_id)->toBe($story->id)
        ->and($bug->project_id)->toBe($story->epic->project_id)
        ->and($bug->title)->toBe('Login form regression')
        ->and($bug->description)->toContain('login form no longer validates')
        ->and($bug->status)->toBe('new')
        ->and($bug->severity)->toBe('major');
});

test('QA regression bug enters bug intake flow with new status', function () {
    $story = Story::factory()->create();
    $testAgent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    $bug = $workflow->failQaWithRegression($story, $testAgent, 'Regression', 'Description');

    expect($bug->status)->toBe('new')
        ->and(Bug::TRANSITIONS[$bug->status])->toContain('triaged');
});

// --- Merge Change Set ---

test('merge change set merges all PRs and updates change set status', function () {
    $story = Story::factory()->create(['status' => 'staging']);
    $changeSet = ChangeSet::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'status' => 'ready',
        'summary' => 'Changes for merge',
    ]);
    $repo1 = Repo::factory()->create();
    $repo2 = Repo::factory()->create();
    $branch1 = Branch::factory()->create(['repo_id' => $repo1->id]);
    $branch2 = Branch::factory()->create(['repo_id' => $repo2->id]);
    PullRequest::create([
        'change_set_id' => $changeSet->id,
        'branch_id' => $branch1->id,
        'repo_id' => $repo1->id,
        'agent_id' => Agent::factory()->create()->id,
        'title' => 'PR 1',
        'status' => 'approved',
    ]);
    PullRequest::create([
        'change_set_id' => $changeSet->id,
        'branch_id' => $branch2->id,
        'repo_id' => $repo2->id,
        'agent_id' => Agent::factory()->create()->id,
        'title' => 'PR 2',
        'status' => 'approved',
    ]);
    $workflow = new StoryReviewReleaseWorkflow;

    $result = $workflow->mergeChangeSet($changeSet);

    expect($result->fresh()->status)->toBe('merged');

    $changeSet->pullRequests->each(function ($pr) {
        expect($pr->fresh()->status)->toBe('merged');
    });
});

// --- Mark Merged ---

test('mark merged transitions story from staging to merged', function () {
    $story = Story::factory()->create(['status' => 'staging']);
    $workflow = new StoryReviewReleaseWorkflow;

    $result = $workflow->markMerged($story);

    expect($result->fresh()->status)->toBe('merged');
});

// --- Release Approval HITL Escalation ---

test('major version change triggers HITL release approval escalation', function () {
    $story = Story::factory()->create(['status' => 'staging']);
    $agent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    $escalation = $workflow->escalateReleaseApproval($story, $agent, 'Major version bump: v2.0.0 -> v3.0.0');

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->work_item_id)->toBe($story->id)
        ->and($escalation->trigger_type)->toBe('risk')
        ->and($escalation->trigger_class)->toBe('release_approval')
        ->and($escalation->reason)->toContain('Major version bump')
        ->and($escalation->resolved_at)->toBeNull();
});

test('infra change triggers HITL release approval escalation', function () {
    $story = Story::factory()->create(['status' => 'staging']);
    $agent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    $escalation = $workflow->escalateReleaseApproval($story, $agent, 'Infrastructure change: database migration with potential downtime');

    expect($escalation->trigger_class)->toBe('release_approval')
        ->and($escalation->raised_by_agent_id)->toBe($agent->id);
});

// --- Deploy ---

test('deploy transitions story from merged to deployed', function () {
    $story = Story::factory()->create(['status' => 'merged']);
    $workflow = new StoryReviewReleaseWorkflow;

    $result = $workflow->deploy($story);

    expect($result->fresh()->status)->toBe('deployed');
});

// --- Cleanup ---

test('cleanup marks worktrees as stale after deployment', function () {
    $story = Story::factory()->create(['status' => 'deployed']);
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'path' => '/worktrees/test',
        'status' => 'active',
        'last_activity_at' => now(),
    ]);
    $workflow = new StoryReviewReleaseWorkflow;

    $workflow->cleanup($story);

    expect($worktree->fresh()->status)->toBe('stale');
});

// --- Close Story ---

test('close story transitions from deployed to closed_done', function () {
    $story = Story::factory()->create(['status' => 'deployed']);
    $workflow = new StoryReviewReleaseWorkflow;

    $result = $workflow->closeStory($story);

    expect($result->fresh()->status)->toBe('closed_done');
});

// --- Full Review Loop ---

test('full review loop: in_development -> in_review -> changes_requested -> in_development -> in_review -> staging', function () {
    $story = Story::factory()->create(['status' => 'in_development', 'dev_iteration_count' => 1]);
    $reviewAgent = Agent::factory()->create();
    $designCriticAgent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $changeSet = ChangeSet::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'status' => 'draft',
        'summary' => 'Test changes',
    ]);
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $pr = PullRequest::create([
        'change_set_id' => $changeSet->id,
        'branch_id' => $branch->id,
        'repo_id' => $repo->id,
        'agent_id' => Agent::factory()->create()->id,
        'title' => 'Story PR',
        'status' => 'open',
    ]);
    $workflow = new StoryReviewReleaseWorkflow;

    // Step 1: Submit for review
    $workflow->submitForReview($story);
    expect($story->fresh()->status)->toBe('in_review');

    // Step 2: Reviewer requests changes
    $review = $workflow->reviewPullRequest($pr, $reviewAgent, 'changes_requested', 'Needs work');
    expect($review->status)->toBe('changes_requested');

    // Step 3: Design critic re-run and return to development
    $critique = $workflow->handleChangesRequested($story->fresh(), $designCriticAgent);
    expect($story->fresh()->status)->toBe('in_development')
        ->and($critique->critic_type)->toBe('design');

    // Step 4: Re-submit for review
    $workflow->submitForReview($story->fresh());
    expect($story->fresh()->status)->toBe('in_review');

    // Step 5: Reviewer approves
    $approval = $workflow->reviewPullRequest($pr, $reviewAgent, 'approved', 'LGTM');
    expect($approval->status)->toBe('approved');

    // Step 6: QA passes
    $workflow->passQa($story->fresh());
    expect($story->fresh()->status)->toBe('staging');
});

// --- QA Failure Bug Creation Flow ---

test('full QA failure flow: review passes but QA finds regression, bug is filed', function () {
    $story = Story::factory()->create(['status' => 'in_development']);
    $reviewAgent = Agent::factory()->create();
    $testAgent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    // Submit and review
    $workflow->submitForReview($story);
    expect($story->fresh()->status)->toBe('in_review');

    // QA finds regression
    $bug = $workflow->failQaWithRegression(
        $story,
        $testAgent,
        'Button click handler broken',
        'The submit button no longer fires the click event',
    );

    expect($bug)->toBeInstanceOf(Bug::class)
        ->and($bug->status)->toBe('new')
        ->and($bug->linked_story_id)->toBe($story->id)
        ->and($bug->severity)->toBe('major');
});

// --- Full Release Flow ---

test('full release flow: staging -> merge -> deploy -> cleanup -> close', function () {
    $story = Story::factory()->create(['status' => 'staging']);
    $releaseAgent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'path' => '/worktrees/release',
        'status' => 'active',
        'last_activity_at' => now(),
    ]);
    $changeSet = ChangeSet::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'status' => 'ready',
        'summary' => 'Release changes',
    ]);
    $pr = PullRequest::create([
        'change_set_id' => $changeSet->id,
        'branch_id' => $branch->id,
        'repo_id' => $repo->id,
        'agent_id' => $releaseAgent->id,
        'title' => 'Release PR',
        'status' => 'approved',
    ]);
    $workflow = new StoryReviewReleaseWorkflow;

    // Step 1: Merge all PRs
    $workflow->mergeChangeSet($changeSet);
    expect($pr->fresh()->status)->toBe('merged')
        ->and($changeSet->fresh()->status)->toBe('merged');

    // Step 2: Mark story as merged
    $workflow->markMerged($story);
    expect($story->fresh()->status)->toBe('merged');

    // Step 3: Deploy
    $workflow->deploy($story->fresh());
    expect($story->fresh()->status)->toBe('deployed');

    // Step 4: Cleanup worktrees
    $workflow->cleanup($story->fresh());
    expect($worktree->fresh()->status)->toBe('stale');

    // Step 5: Close story
    $workflow->closeStory($story->fresh());
    expect($story->fresh()->status)->toBe('closed_done');
});

// --- Release with HITL Escalation ---

test('release with HITL approval: escalation blocks then resolves', function () {
    $story = Story::factory()->create(['status' => 'staging']);
    $releaseAgent = Agent::factory()->create();
    $workflow = new StoryReviewReleaseWorkflow;

    // Escalate for release approval
    $escalation = $workflow->escalateReleaseApproval($story, $releaseAgent, 'Major version bump');
    expect($story->hasUnresolvedEscalation())->toBeTrue();

    // Resolve the escalation
    $escalation->update([
        'resolution' => 'Approved by engineering lead',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);
    expect($story->fresh()->hasUnresolvedEscalation())->toBeFalse();

    // Now merge and deploy proceed
    $workflow->markMerged($story);
    $workflow->deploy($story->fresh());
    expect($story->fresh()->status)->toBe('deployed');
});
