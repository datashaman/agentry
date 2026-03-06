<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\Repo;
use App\Models\Story;

class StoryDevelopmentWorkflow
{
    public function __construct(
        protected GitHubAppService $github,
    ) {}

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
     * Derive the branch name for a story.
     */
    public function branchName(Story $story): string
    {
        return 'feature/story-'.$story->id;
    }

    /**
     * Create a branch on GitHub for the story on the given repo.
     */
    public function createBranch(Story $story, Repo $repo): bool
    {
        return $this->github->createBranch(
            $repo,
            $this->branchName($story),
            $repo->default_branch ?? 'main',
        );
    }

    /**
     * Create a pull request on GitHub for the story.
     *
     * @return array{number: int, html_url: string}|null
     */
    public function createPullRequest(Story $story, Repo $repo): ?array
    {
        return $this->github->createPullRequest(
            $repo,
            'Story #'.$story->id.': '.$story->title,
            $this->branchName($story),
            $repo->default_branch ?? 'main',
            $story->description ?? '',
        );
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
     * Handle blocked status.
     */
    public function handleBlocked(Story $story): Story
    {
        $story->transitionTo('blocked');

        return $story;
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
