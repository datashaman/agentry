<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Story;
use App\Services\GitHubAppService;
use App\Services\StoryReviewReleaseWorkflow;
use Illuminate\Support\Facades\Http;

// --- Submit for Review ---

test('submit for review transitions story from in_development to in_review', function () {
    $story = Story::factory()->create(['status' => 'in_development']);
    $workflow = app(StoryReviewReleaseWorkflow::class);

    $result = $workflow->submitForReview($story);

    expect($result->fresh()->status)->toBe('in_review');
});

// --- Changes Needed - Return to Development ---

test('changes requested triggers design critic re-run and returns to development', function () {
    $story = Story::factory()->create(['status' => 'in_review', 'dev_iteration_count' => 1]);
    $designCriticAgent = Agent::factory()->create();
    $workflow = app(StoryReviewReleaseWorkflow::class);

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
    $workflow = app(StoryReviewReleaseWorkflow::class);

    $escalation = $workflow->escalateCodeReview($story, $agent, 'Security-sensitive authentication changes detected');

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->work_item_id)->toBe($story->id)
        ->and($escalation->work_item_type)->toBe(Story::class)
        ->and($escalation->trigger_type)->toBe('risk')
        ->and($escalation->trigger_class)->toBe('code_review')
        ->and($escalation->reason)->toBe('Security-sensitive authentication changes detected')
        ->and($escalation->resolved_at)->toBeNull();
});

// --- QA Pass ---

test('pass QA transitions story from in_review to staging', function () {
    $story = Story::factory()->create(['status' => 'in_review']);
    $workflow = app(StoryReviewReleaseWorkflow::class);

    $result = $workflow->passQa($story);

    expect($result->fresh()->status)->toBe('staging');
});

// --- QA Failure - Bug Creation ---

test('QA regression files a bug linked to the originating story', function () {
    $story = Story::factory()->create(['status' => 'in_review']);
    $testAgent = Agent::factory()->create();
    $workflow = app(StoryReviewReleaseWorkflow::class);

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

// --- Merge Pull Requests via GitHub API ---

test('merge pull requests merges all open PRs for story branch', function () {
    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id, 'url' => 'https://github.com/acme/app']);
    $story = Story::factory()->create();

    $mock = Mockery::mock(GitHubAppService::class)->makePartial();
    $mock->shouldReceive('getInstallationToken')->andReturn('test-token');
    app()->instance(GitHubAppService::class, $mock);

    Http::fake([
        'api.github.com/repos/acme/app/pulls*' => Http::response([
            ['number' => 10, 'state' => 'open'],
        ]),
        'api.github.com/repos/acme/app/pulls/10/merge' => Http::response(['merged' => true]),
    ]);

    $workflow = app(StoryReviewReleaseWorkflow::class);

    $workflow->mergePullRequests($story, [$repo]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'pulls/10/merge'));
});

// --- Mark Merged ---

test('mark merged transitions story from staging to merged', function () {
    $story = Story::factory()->create(['status' => 'staging']);
    $workflow = app(StoryReviewReleaseWorkflow::class);

    $result = $workflow->markMerged($story);

    expect($result->fresh()->status)->toBe('merged');
});

// --- Release Approval HITL Escalation ---

test('major version change triggers HITL release approval escalation', function () {
    $story = Story::factory()->create(['status' => 'staging']);
    $agent = Agent::factory()->create();
    $workflow = app(StoryReviewReleaseWorkflow::class);

    $escalation = $workflow->escalateReleaseApproval($story, $agent, 'Major version bump: v2.0.0 -> v3.0.0');

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->work_item_id)->toBe($story->id)
        ->and($escalation->trigger_type)->toBe('risk')
        ->and($escalation->trigger_class)->toBe('release_approval')
        ->and($escalation->reason)->toContain('Major version bump')
        ->and($escalation->resolved_at)->toBeNull();
});

// --- Deploy ---

test('deploy transitions story from merged to deployed', function () {
    $story = Story::factory()->create(['status' => 'merged']);
    $workflow = app(StoryReviewReleaseWorkflow::class);

    $result = $workflow->deploy($story);

    expect($result->fresh()->status)->toBe('deployed');
});

// --- Cleanup ---

test('cleanup deletes branch on GitHub after deployment', function () {
    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id, 'url' => 'https://github.com/acme/app']);
    $story = Story::factory()->create(['status' => 'deployed']);

    $mock = Mockery::mock(GitHubAppService::class)->makePartial();
    $mock->shouldReceive('getInstallationToken')->andReturn('test-token');
    app()->instance(GitHubAppService::class, $mock);

    Http::fake([
        'api.github.com/repos/acme/app/git/refs/heads/*' => Http::response(null, 204),
    ]);

    $workflow = app(StoryReviewReleaseWorkflow::class);

    $workflow->cleanup($story, [$repo]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'git/refs/heads/feature/story-'.$story->id)
        && $request->method() === 'DELETE');
});

// --- Close Story ---

test('close story transitions from deployed to closed_done', function () {
    $story = Story::factory()->create(['status' => 'deployed']);
    $workflow = app(StoryReviewReleaseWorkflow::class);

    $result = $workflow->closeStory($story);

    expect($result->fresh()->status)->toBe('closed_done');
});

// --- Full Review Loop ---

test('full review loop: in_development -> in_review -> changes_requested -> in_development -> in_review -> staging', function () {
    $story = Story::factory()->create(['status' => 'in_development', 'dev_iteration_count' => 1]);
    $designCriticAgent = Agent::factory()->create();
    $workflow = app(StoryReviewReleaseWorkflow::class);

    // Step 1: Submit for review
    $workflow->submitForReview($story);
    expect($story->fresh()->status)->toBe('in_review');

    // Step 2: Design critic re-run and return to development
    $critique = $workflow->handleChangesRequested($story->fresh(), $designCriticAgent);
    expect($story->fresh()->status)->toBe('in_development')
        ->and($critique->critic_type)->toBe('design');

    // Step 3: Re-submit for review
    $workflow->submitForReview($story->fresh());
    expect($story->fresh()->status)->toBe('in_review');

    // Step 4: QA passes
    $workflow->passQa($story->fresh());
    expect($story->fresh()->status)->toBe('staging');
});

// --- Release with HITL Escalation ---

test('release with HITL approval: escalation blocks then resolves', function () {
    $story = Story::factory()->create(['status' => 'staging']);
    $releaseAgent = Agent::factory()->create();
    $workflow = app(StoryReviewReleaseWorkflow::class);

    $escalation = $workflow->escalateReleaseApproval($story, $releaseAgent, 'Major version bump');
    expect($story->hasUnresolvedEscalation())->toBeTrue();

    $escalation->update([
        'resolution' => 'Approved by engineering lead',
        'resolved_by' => 'admin@example.com',
        'resolved_at' => now(),
    ]);
    expect($story->fresh()->hasUnresolvedEscalation())->toBeFalse();

    $workflow->markMerged($story);
    $workflow->deploy($story->fresh());
    expect($story->fresh()->status)->toBe('deployed');
});
