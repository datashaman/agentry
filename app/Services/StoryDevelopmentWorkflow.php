<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\ChangeSet;
use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\PullRequest;
use App\Models\Repo;
use App\Models\Story;
use App\Models\Worktree;

class StoryDevelopmentWorkflow
{
    /**
     * Run design critique before coding begins, producing a Critique of type "design".
     */
    public function runDesignCritique(Story $story, Agent $designCriticAgent): Critique
    {
        $story->transitionTo('design_critique');

        return Critique::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'agent_id' => $designCriticAgent->id,
            'critic_type' => 'design',
            'revision' => 1,
            'severity' => 'suggestion',
            'disposition' => 'pending',
        ]);
    }

    /**
     * Check worktree state and return the appropriate action.
     *
     * Returns: ['action' => 'create'|'resume'|'escalate', 'worktree' => ?Worktree]
     *
     * @return array{action: string, worktree: ?Worktree}
     */
    public function checkWorktree(Story $story, Repo $repo): array
    {
        $activeWorktree = Worktree::where('repo_id', $repo->id)
            ->where('status', 'active')
            ->first();

        if (! $activeWorktree) {
            return ['action' => 'create', 'worktree' => null];
        }

        if ($activeWorktree->work_item_type === Story::class && $activeWorktree->work_item_id === $story->id) {
            return ['action' => 'resume', 'worktree' => $activeWorktree];
        }

        return ['action' => 'escalate', 'worktree' => $activeWorktree];
    }

    /**
     * Create a fresh worktree for the story on the given repo.
     */
    public function createWorktree(Story $story, Repo $repo, Branch $branch): Worktree
    {
        return Worktree::create([
            'repo_id' => $repo->id,
            'branch_id' => $branch->id,
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'path' => '/worktrees/'.$repo->name.'/story-'.$story->id,
            'status' => 'active',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Resume an existing worktree by updating its last activity timestamp.
     */
    public function resumeWorktree(Worktree $worktree): Worktree
    {
        $worktree->update(['last_activity_at' => now()]);

        return $worktree;
    }

    /**
     * Escalate when a different work item's worktree is active on the repo.
     */
    public function escalateWorktreeConflict(Story $story, Worktree $conflictingWorktree, Agent $agent): HitlEscalation
    {
        return HitlEscalation::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'ambiguity',
            'trigger_class' => 'worktree_conflict',
            'reason' => 'Active worktree for different work item exists on repo. Conflicting worktree ID: '.$conflictingWorktree->id,
        ]);
    }

    /**
     * Create a ChangeSet grouping branches and PRs across affected repos.
     */
    public function createChangeSet(Story $story, Agent $codingAgent, array $repos): ChangeSet
    {
        $changeSet = ChangeSet::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'status' => 'draft',
            'summary' => 'Changes for story: '.$story->title,
        ]);

        foreach ($repos as $repo) {
            $branch = Branch::create([
                'repo_id' => $repo->id,
                'name' => 'feature/story-'.$story->id,
                'base_branch' => $repo->default_branch ?? 'main',
                'work_item_id' => $story->id,
                'work_item_type' => Story::class,
            ]);

            PullRequest::create([
                'change_set_id' => $changeSet->id,
                'branch_id' => $branch->id,
                'repo_id' => $repo->id,
                'agent_id' => $codingAgent->id,
                'title' => 'Story #'.$story->id.': '.$story->title,
                'status' => 'open',
            ]);
        }

        return $changeSet;
    }

    /**
     * Handle blocked status — Planning Agent attempts resolution.
     */
    public function handleBlocked(Story $story): Story
    {
        $story->transitionTo('blocked');

        return $story;
    }

    /**
     * Escalate cross-team blocker to HITL.
     */
    public function escalateCrossTeamBlocker(Story $story, Agent $agent, string $reason): HitlEscalation
    {
        return HitlEscalation::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'policy',
            'trigger_class' => 'cross_team_blocker',
            'reason' => $reason,
        ]);
    }

    /**
     * Resolve blocked status and return to in_development.
     */
    public function resolveBlocked(Story $story): Story
    {
        $story->transitionTo('in_development');

        return $story;
    }

    /**
     * Start development phase: transition from design_critique to in_development.
     */
    public function startDevelopment(Story $story, Critique $designCritique): Story
    {
        $designCritique->update(['disposition' => 'accepted']);

        $story->transitionTo('in_development');
        $story->increment('dev_iteration_count');

        return $story;
    }
}
