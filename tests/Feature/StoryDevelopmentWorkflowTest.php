<?php

use App\Models\Agent;
use App\Models\Branch;
use App\Models\ChangeSet;
use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\Repo;
use App\Models\Story;
use App\Models\Worktree;
use App\Services\StoryDevelopmentWorkflow;

// --- Design Critique ---

test('design critique transitions story to design_critique and creates design critique', function () {
    $story = Story::factory()->create(['status' => 'sprint_planned']);
    $agent = Agent::factory()->create();
    $workflow = new StoryDevelopmentWorkflow;

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

// --- Worktree Check: Create Fresh ---

test('check worktree returns create when no active worktree exists for repo', function () {
    $story = Story::factory()->create();
    $repo = Repo::factory()->create();
    $workflow = new StoryDevelopmentWorkflow;

    $result = $workflow->checkWorktree($story, $repo);

    expect($result['action'])->toBe('create')
        ->and($result['worktree'])->toBeNull();
});

test('create worktree creates a fresh worktree for the story', function () {
    $story = Story::factory()->create();
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $workflow = new StoryDevelopmentWorkflow;

    $worktree = $workflow->createWorktree($story, $repo, $branch);

    expect($worktree)->toBeInstanceOf(Worktree::class)
        ->and($worktree->repo_id)->toBe($repo->id)
        ->and($worktree->branch_id)->toBe($branch->id)
        ->and($worktree->work_item_id)->toBe($story->id)
        ->and($worktree->work_item_type)->toBe(Story::class)
        ->and($worktree->status)->toBe('active')
        ->and($worktree->last_activity_at)->not->toBeNull();
});

// --- Worktree Check: Resume ---

test('check worktree returns resume when same work item worktree is active', function () {
    $story = Story::factory()->create();
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
    $workflow = new StoryDevelopmentWorkflow;

    $result = $workflow->checkWorktree($story, $repo);

    expect($result['action'])->toBe('resume')
        ->and($result['worktree']->id)->toBe($worktree->id);
});

test('resume worktree updates last activity timestamp', function () {
    $story = Story::factory()->create();
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $originalTime = now()->subHour();
    $worktree = Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'path' => '/worktrees/test',
        'status' => 'active',
        'last_activity_at' => $originalTime,
    ]);
    $workflow = new StoryDevelopmentWorkflow;

    $resumed = $workflow->resumeWorktree($worktree);

    expect($resumed->id)->toBe($worktree->id)
        ->and($resumed->fresh()->last_activity_at->gt($originalTime))->toBeTrue();
});

// --- Worktree Check: Escalate ---

test('check worktree returns escalate when different work item worktree is active', function () {
    $story = Story::factory()->create();
    $otherStory = Story::factory()->create();
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $worktree = Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $otherStory->id,
        'work_item_type' => Story::class,
        'path' => '/worktrees/other',
        'status' => 'active',
        'last_activity_at' => now(),
    ]);
    $workflow = new StoryDevelopmentWorkflow;

    $result = $workflow->checkWorktree($story, $repo);

    expect($result['action'])->toBe('escalate')
        ->and($result['worktree']->id)->toBe($worktree->id);
});

test('escalate worktree conflict creates HITL escalation', function () {
    $story = Story::factory()->create();
    $agent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $conflictingWorktree = Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => Story::factory()->create()->id,
        'work_item_type' => Story::class,
        'path' => '/worktrees/conflict',
        'status' => 'active',
        'last_activity_at' => now(),
    ]);
    $workflow = new StoryDevelopmentWorkflow;

    $escalation = $workflow->escalateWorktreeConflict($story, $conflictingWorktree, $agent);

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->work_item_id)->toBe($story->id)
        ->and($escalation->work_item_type)->toBe(Story::class)
        ->and($escalation->trigger_type)->toBe('ambiguity')
        ->and($escalation->trigger_class)->toBe('worktree_conflict')
        ->and($escalation->resolved_at)->toBeNull();
});

test('check worktree ignores interrupted worktrees', function () {
    $story = Story::factory()->create();
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => Story::factory()->create()->id,
        'work_item_type' => Story::class,
        'path' => '/worktrees/interrupted',
        'status' => 'interrupted',
        'last_activity_at' => now(),
    ]);
    $workflow = new StoryDevelopmentWorkflow;

    $result = $workflow->checkWorktree($story, $repo);

    expect($result['action'])->toBe('create');
});

// --- ChangeSet Creation ---

test('create change set creates branches and PRs across affected repos', function () {
    $story = Story::factory()->create(['title' => 'Add feature X']);
    $agent = Agent::factory()->create();
    $repo1 = Repo::factory()->create(['name' => 'frontend', 'default_branch' => 'main']);
    $repo2 = Repo::factory()->create(['name' => 'backend', 'default_branch' => 'develop']);
    $workflow = new StoryDevelopmentWorkflow;

    $changeSet = $workflow->createChangeSet($story, $agent, [$repo1, $repo2]);

    expect($changeSet)->toBeInstanceOf(ChangeSet::class)
        ->and($changeSet->work_item_id)->toBe($story->id)
        ->and($changeSet->work_item_type)->toBe(Story::class)
        ->and($changeSet->status)->toBe('draft');

    $pullRequests = $changeSet->pullRequests;
    expect($pullRequests)->toHaveCount(2);

    $pr1 = $pullRequests->firstWhere('repo_id', $repo1->id);
    expect($pr1->branch->repo_id)->toBe($repo1->id)
        ->and($pr1->branch->base_branch)->toBe('main')
        ->and($pr1->agent_id)->toBe($agent->id)
        ->and($pr1->status)->toBe('open');

    $pr2 = $pullRequests->firstWhere('repo_id', $repo2->id);
    expect($pr2->branch->repo_id)->toBe($repo2->id)
        ->and($pr2->branch->base_branch)->toBe('develop');
});

test('create change set creates branches linked to story', function () {
    $story = Story::factory()->create();
    $agent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $workflow = new StoryDevelopmentWorkflow;

    $changeSet = $workflow->createChangeSet($story, $agent, [$repo]);

    $branch = Branch::where('repo_id', $repo->id)->where('work_item_id', $story->id)->first();
    expect($branch)->not->toBeNull()
        ->and($branch->work_item_type)->toBe(Story::class)
        ->and($branch->name)->toBe('feature/story-'.$story->id);
});

test('create change set with single repo', function () {
    $story = Story::factory()->create();
    $agent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $workflow = new StoryDevelopmentWorkflow;

    $changeSet = $workflow->createChangeSet($story, $agent, [$repo]);

    expect($changeSet->pullRequests)->toHaveCount(1);
});

// --- Blocked Status ---

test('handle blocked transitions story to blocked', function () {
    $story = Story::factory()->create(['status' => 'in_development']);
    $workflow = new StoryDevelopmentWorkflow;

    $result = $workflow->handleBlocked($story);

    expect($result->fresh()->status)->toBe('blocked');
});

test('escalate cross team blocker creates HITL escalation', function () {
    $story = Story::factory()->create(['status' => 'in_development']);
    $agent = Agent::factory()->create();
    $workflow = new StoryDevelopmentWorkflow;

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
    $workflow = new StoryDevelopmentWorkflow;

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
    $workflow = new StoryDevelopmentWorkflow;

    $result = $workflow->startDevelopment($story, $critique);

    expect($result->fresh()->status)->toBe('in_development')
        ->and($result->fresh()->dev_iteration_count)->toBe(1)
        ->and($critique->fresh()->disposition)->toBe('accepted');
});

// --- Full Development Phase Flow ---

test('full development flow: sprint_planned -> design_critique -> in_development with worktree and changeset', function () {
    $story = Story::factory()->create(['status' => 'sprint_planned', 'title' => 'Implement feature']);
    $designCriticAgent = Agent::factory()->create();
    $codingAgent = Agent::factory()->create();
    $repo = Repo::factory()->create(['name' => 'main-app']);
    $workflow = new StoryDevelopmentWorkflow;

    // Step 1: Run design critique
    $critique = $workflow->runDesignCritique($story, $designCriticAgent);
    expect($story->fresh()->status)->toBe('design_critique');

    // Step 2: Start development
    $workflow->startDevelopment($story, $critique);
    expect($story->fresh()->status)->toBe('in_development');

    // Step 3: Check worktree (should create fresh)
    $result = $workflow->checkWorktree($story, $repo);
    expect($result['action'])->toBe('create');

    // Step 4: Create worktree
    $branch = Branch::factory()->create(['repo_id' => $repo->id, 'work_item_id' => $story->id, 'work_item_type' => Story::class]);
    $worktree = $workflow->createWorktree($story, $repo, $branch);
    expect($worktree->status)->toBe('active');

    // Step 5: Create change set
    $changeSet = $workflow->createChangeSet($story, $codingAgent, [$repo]);
    expect($changeSet->status)->toBe('draft')
        ->and($changeSet->pullRequests)->toHaveCount(1);
});

test('full development flow with worktree resume', function () {
    $story = Story::factory()->create(['status' => 'design_critique']);
    $agent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    $existingWorktree = Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'path' => '/worktrees/existing',
        'status' => 'active',
        'last_activity_at' => now()->subDay(),
    ]);
    $workflow = new StoryDevelopmentWorkflow;

    $result = $workflow->checkWorktree($story, $repo);
    expect($result['action'])->toBe('resume');

    $resumed = $workflow->resumeWorktree($result['worktree']);
    expect($resumed->id)->toBe($existingWorktree->id);
});

test('full development flow with worktree conflict escalation', function () {
    $story = Story::factory()->create(['status' => 'design_critique']);
    $otherStory = Story::factory()->create();
    $agent = Agent::factory()->create();
    $repo = Repo::factory()->create();
    $branch = Branch::factory()->create(['repo_id' => $repo->id]);
    Worktree::create([
        'repo_id' => $repo->id,
        'branch_id' => $branch->id,
        'work_item_id' => $otherStory->id,
        'work_item_type' => Story::class,
        'path' => '/worktrees/other',
        'status' => 'active',
        'last_activity_at' => now(),
    ]);
    $workflow = new StoryDevelopmentWorkflow;

    $result = $workflow->checkWorktree($story, $repo);
    expect($result['action'])->toBe('escalate');

    $escalation = $workflow->escalateWorktreeConflict($story, $result['worktree'], $agent);
    expect($escalation->trigger_class)->toBe('worktree_conflict')
        ->and($story->hasUnresolvedEscalation())->toBeTrue();
});

test('blocked and resolved flow: in_development -> blocked -> in_development', function () {
    $story = Story::factory()->create(['status' => 'in_development']);
    $workflow = new StoryDevelopmentWorkflow;

    $workflow->handleBlocked($story);
    expect($story->fresh()->status)->toBe('blocked');

    $workflow->resolveBlocked($story);
    expect($story->fresh()->status)->toBe('in_development');
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

test('design_critique to blocked transition works in state machine', function () {
    $story = Story::factory()->create(['status' => 'design_critique']);

    $story->transitionTo('blocked');

    expect($story->fresh()->status)->toBe('blocked');
});
