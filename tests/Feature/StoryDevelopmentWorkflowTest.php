<?php

use App\Models\Agent;
use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Story;
use App\Services\GitHubAppService;
use App\Services\StoryDevelopmentWorkflow;
use Illuminate\Support\Facades\Http;

// --- Design Critique ---

test('design critique transitions story to design_critique and creates design critique', function () {
    $story = Story::factory()->create(['status' => 'sprint_planned']);
    $agent = Agent::factory()->create();
    $workflow = app(StoryDevelopmentWorkflow::class);

    $critique = $workflow->runDesignCritique($story, $agent);

    expect($story->fresh()->status)->toBe('design_critique')
        ->and($critique)->toBeInstanceOf(Critique::class)
        ->and($critique->critic_type)->toBe('design')
        ->and($critique->work_item_id)->toBe($story->id)
        ->and($critique->work_item_type)->toBe(Story::class)
        ->and($critique->agent_id)->toBe($agent->id)
        ->and($critique->revision)->toBe(1)
        ->and($critique->disposition)->toBe('pending');
});

// --- Branch Name ---

test('branch name follows convention for story', function () {
    $story = Story::factory()->create();
    $workflow = app(StoryDevelopmentWorkflow::class);

    expect($workflow->branchName($story))->toBe('feature/story-'.$story->id);
});

// --- Create Branch via GitHub API ---

test('create branch calls GitHub API to create branch from default branch', function () {
    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id, 'default_branch' => 'main', 'url' => 'https://github.com/acme/app']);
    $story = Story::factory()->create();

    $mock = Mockery::mock(GitHubAppService::class)->makePartial();
    $mock->shouldReceive('getInstallationToken')->andReturn('test-token');
    app()->instance(GitHubAppService::class, $mock);

    Http::fake([
        'api.github.com/repos/acme/app/git/ref/heads/main' => Http::response(['object' => ['sha' => 'abc123']]),
        'api.github.com/repos/acme/app/git/refs' => Http::response(['ref' => 'refs/heads/feature/story-'.$story->id], 201),
    ]);

    $workflow = app(StoryDevelopmentWorkflow::class);

    $result = $workflow->createBranch($story, $repo);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) use ($story) {
        return str_contains($request->url(), 'git/refs')
            && $request['ref'] === 'refs/heads/feature/story-'.$story->id
            && $request['sha'] === 'abc123';
    });
});

// --- Create Pull Request via GitHub API ---

test('create pull request calls GitHub API', function () {
    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create(['project_id' => $project->id, 'default_branch' => 'main', 'url' => 'https://github.com/acme/app']);
    $story = Story::factory()->create(['title' => 'Add login']);

    $mock = Mockery::mock(GitHubAppService::class)->makePartial();
    $mock->shouldReceive('getInstallationToken')->andReturn('test-token');
    app()->instance(GitHubAppService::class, $mock);

    Http::fake([
        'api.github.com/repos/acme/app/pulls' => Http::response(['number' => 42, 'html_url' => 'https://github.com/acme/app/pull/42'], 201),
    ]);

    $workflow = app(StoryDevelopmentWorkflow::class);

    $result = $workflow->createPullRequest($story, $repo);

    expect($result)->not->toBeNull()
        ->and($result['number'])->toBe(42)
        ->and($result['html_url'])->toBe('https://github.com/acme/app/pull/42');
});

// --- Blocked Status ---

test('handle blocked transitions story to blocked', function () {
    $story = Story::factory()->create(['status' => 'in_development']);
    $workflow = app(StoryDevelopmentWorkflow::class);

    $result = $workflow->handleBlocked($story);

    expect($result->fresh()->status)->toBe('blocked');
});

test('escalate cross team blocker creates HITL escalation', function () {
    $story = Story::factory()->create(['status' => 'in_development']);
    $agent = Agent::factory()->create();
    $workflow = app(StoryDevelopmentWorkflow::class);

    $escalation = $workflow->escalateCrossTeamBlocker($story, $agent, 'Blocked by team-backend API change');

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->work_item_id)->toBe($story->id)
        ->and($escalation->trigger_type)->toBe('policy')
        ->and($escalation->trigger_class)->toBe('cross_team_blocker')
        ->and($escalation->reason)->toBe('Blocked by team-backend API change')
        ->and($escalation->resolved_at)->toBeNull();
});

test('resolve blocked transitions story back to in_development', function () {
    $story = Story::factory()->create(['status' => 'blocked']);
    $workflow = app(StoryDevelopmentWorkflow::class);

    $result = $workflow->resolveBlocked($story);

    expect($result->fresh()->status)->toBe('in_development');
});

// --- Start Development ---

test('start development accepts design critique and transitions to in_development', function () {
    $story = Story::factory()->create(['status' => 'design_critique', 'dev_iteration_count' => 0]);
    $agent = Agent::factory()->create();
    $critique = Critique::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'agent_id' => $agent->id,
        'critic_type' => 'design',
        'disposition' => 'pending',
    ]);
    $workflow = app(StoryDevelopmentWorkflow::class);

    $result = $workflow->startDevelopment($story, $critique);

    expect($result->fresh()->status)->toBe('in_development')
        ->and($result->fresh()->dev_iteration_count)->toBe(1)
        ->and($critique->fresh()->disposition)->toBe('accepted');
});

// --- State Machine Integration ---

test('design_critique to in_development transition works in state machine', function () {
    $story = Story::factory()->create(['status' => 'design_critique']);

    $story->transitionTo('in_development');

    expect($story->fresh()->status)->toBe('in_development');
});

test('sprint_planned to design_critique transition works in state machine', function () {
    $story = Story::factory()->create(['status' => 'sprint_planned']);

    $story->transitionTo('design_critique');

    expect($story->fresh()->status)->toBe('design_critique');
});

test('blocked and resolved flow: in_development -> blocked -> in_development', function () {
    $story = Story::factory()->create(['status' => 'in_development']);
    $workflow = app(StoryDevelopmentWorkflow::class);

    $workflow->handleBlocked($story);
    expect($story->fresh()->status)->toBe('blocked');

    $workflow->resolveBlocked($story);
    expect($story->fresh()->status)->toBe('in_development');
});
