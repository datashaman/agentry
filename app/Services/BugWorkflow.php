<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\Bug;
use App\Models\ChangeSet;
use App\Models\Dependency;
use App\Models\HitlEscalation;
use App\Models\PullRequest;
use App\Models\Repo;
use App\Models\Story;
use App\Models\Worktree;

class BugWorkflow
{
    /**
     * Triage Agent deduplicates, classifies, and sets severity/priority.
     */
    public function triage(Bug $bug, Agent $triageAgent, string $severity, int $priority): Bug
    {
        $bug->update([
            'severity' => $severity,
            'priority' => $priority,
            'assigned_agent_id' => $triageAgent->id,
        ]);

        $bug->transitionTo('triaged');

        return $bug;
    }

    /**
     * Close as duplicate from new status.
     */
    public function closeDuplicate(Bug $bug): Bug
    {
        $bug->transitionTo('closed_duplicate');

        return $bug;
    }

    /**
     * Close as can't reproduce from new status.
     */
    public function closeCantReproduce(Bug $bug): Bug
    {
        $bug->transitionTo('closed_cant_reproduce');

        return $bug;
    }

    /**
     * Escalate data loss/security/ambiguous P0 bugs for HITL Triage Review.
     */
    public function escalateTriageReview(Bug $bug, Agent $agent, string $reason): HitlEscalation
    {
        return HitlEscalation::create([
            'work_item_id' => $bug->id,
            'work_item_type' => Bug::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'risk',
            'trigger_class' => 'triage_review',
            'reason' => $reason,
        ]);
    }

    /**
     * Escalate P0 bugs requiring HITL sign-off before work begins.
     */
    public function escalateP0SignOff(Bug $bug, Agent $agent): HitlEscalation
    {
        return HitlEscalation::create([
            'work_item_id' => $bug->id,
            'work_item_type' => Bug::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'policy',
            'trigger_class' => 'p0_sign_off',
            'reason' => 'P0 bug requires human sign-off before work begins.',
        ]);
    }

    /**
     * Start fix phase — transition bug to in_progress.
     */
    public function startFix(Bug $bug): Bug
    {
        $bug->transitionTo('in_progress');

        return $bug;
    }

    /**
     * Check worktree state for the bug on a given repo.
     *
     * @return array{action: string, worktree: ?Worktree}
     */
    public function checkWorktree(Bug $bug, Repo $repo): array
    {
        $activeWorktree = Worktree::where('repo_id', $repo->id)
            ->where('status', 'active')
            ->first();

        if (! $activeWorktree) {
            return ['action' => 'create', 'worktree' => null];
        }

        if ($activeWorktree->work_item_type === Bug::class && $activeWorktree->work_item_id === $bug->id) {
            return ['action' => 'resume', 'worktree' => $activeWorktree];
        }

        return ['action' => 'escalate', 'worktree' => $activeWorktree];
    }

    /**
     * Create a fresh worktree for the bug fix.
     */
    public function createWorktree(Bug $bug, Repo $repo, Branch $branch): Worktree
    {
        return Worktree::create([
            'repo_id' => $repo->id,
            'branch_id' => $branch->id,
            'work_item_id' => $bug->id,
            'work_item_type' => Bug::class,
            'path' => '/worktrees/'.$repo->name.'/bug-'.$bug->id,
            'status' => 'active',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Create a ChangeSet grouping branches and PRs across affected repos.
     */
    public function createChangeSet(Bug $bug, Agent $codingAgent, array $repos): ChangeSet
    {
        $changeSet = ChangeSet::create([
            'work_item_id' => $bug->id,
            'work_item_type' => Bug::class,
            'status' => 'draft',
            'summary' => 'Fix for bug: '.$bug->title,
        ]);

        foreach ($repos as $repo) {
            $branch = Branch::create([
                'repo_id' => $repo->id,
                'name' => 'bugfix/bug-'.$bug->id,
                'base_branch' => $repo->default_branch ?? 'main',
                'work_item_id' => $bug->id,
                'work_item_type' => Bug::class,
            ]);

            PullRequest::create([
                'change_set_id' => $changeSet->id,
                'branch_id' => $branch->id,
                'repo_id' => $repo->id,
                'agent_id' => $codingAgent->id,
                'title' => 'Bug #'.$bug->id.': '.$bug->title,
                'status' => 'open',
            ]);
        }

        return $changeSet;
    }

    /**
     * Submit bug fix for review — transitions to in_review.
     */
    public function submitForReview(Bug $bug): Bug
    {
        $bug->transitionTo('in_review');

        return $bug;
    }

    /**
     * Send back to in_progress when reviewer requests changes.
     */
    public function handleChangesRequested(Bug $bug): Bug
    {
        $bug->transitionTo('in_progress');

        return $bug;
    }

    /**
     * Verify the bug fix.
     */
    public function verify(Bug $bug): Bug
    {
        $bug->transitionTo('verified');

        return $bug;
    }

    /**
     * Release the fix.
     */
    public function release(Bug $bug): Bug
    {
        $bug->transitionTo('released');

        return $bug;
    }

    /**
     * Escalate P0 hotfix deploy for HITL approval.
     */
    public function escalateHotfixApproval(Bug $bug, Agent $agent): HitlEscalation
    {
        return HitlEscalation::create([
            'work_item_id' => $bug->id,
            'work_item_type' => Bug::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'risk',
            'trigger_class' => 'hotfix_approval',
            'reason' => 'P0 hotfix deploy requires human approval.',
        ]);
    }

    /**
     * Close bug as fixed and check if this unblocks any dependent stories.
     *
     * @return array{bug: Bug, unblocked_stories: \Illuminate\Support\Collection<int, Story>}
     */
    public function closeBug(Bug $bug): array
    {
        $bug->transitionTo('closed_fixed');

        // Find stories blocked by this bug that may now be unblocked
        $unblockedStories = collect();

        $dependencies = Dependency::where('blocker_type', Bug::class)
            ->where('blocker_id', $bug->id)
            ->where('blocked_type', Story::class)
            ->get();

        foreach ($dependencies as $dependency) {
            $story = Story::find($dependency->blocked_id);
            if ($story && ! $story->hasUnresolvedBlockers()) {
                $unblockedStories->push($story);
            }
        }

        return ['bug' => $bug, 'unblocked_stories' => $unblockedStories];
    }

    /**
     * Cleanup worktrees after bug release.
     */
    public function cleanup(Bug $bug): void
    {
        $bug->worktrees()->update([
            'status' => 'stale',
        ]);
    }
}
