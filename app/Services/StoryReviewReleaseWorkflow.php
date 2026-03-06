<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\Story;

class StoryReviewReleaseWorkflow
{
    public function __construct(
        protected GitHubAppService $github,
    ) {}

    /**
     * Submit story for review — transitions from in_development to in_review.
     */
    public function submitForReview(Story $story): Story
    {
        $story->transitionTo('in_review');

        return $story;
    }

    /**
     * Handle changes requested — triggers Design Critic re-run and returns to development.
     */
    public function handleChangesRequested(Story $story, Agent $designCriticAgent): Critique
    {
        $story->transitionTo('in_development');

        return Critique::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'agent_id' => $designCriticAgent->id,
            'critic_type' => 'design',
            'revision' => $story->dev_iteration_count + 1,
            'severity' => 'suggestion',
            'disposition' => 'pending',
        ]);
    }

    /**
     * Escalate security surface or breaking API change — creates HITL Code Review escalation.
     */
    public function escalateCodeReview(Story $story, Agent $agent, string $reason): HitlEscalation
    {
        return HitlEscalation::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'risk',
            'trigger_class' => 'code_review',
            'reason' => $reason,
        ]);
    }

    /**
     * Test Agent runs QA and reports success.
     */
    public function passQa(Story $story): Story
    {
        $story->transitionTo('staging');

        return $story;
    }

    /**
     * Test Agent detects regression — files a Bug entering bug intake flow.
     */
    public function failQaWithRegression(Story $story, Agent $testAgent, string $title, string $description): Bug
    {
        $epic = $story->epic;

        return Bug::create([
            'project_id' => $epic->project_id,
            'linked_story_id' => $story->id,
            'title' => $title,
            'description' => $description,
            'status' => 'new',
            'severity' => 'major',
            'priority' => 1,
        ]);
    }

    /**
     * Merge all pull requests for the story branch across repos.
     */
    public function mergePullRequests(Story $story, array $repos): void
    {
        $branchName = 'feature/story-'.$story->id;

        foreach ($repos as $repo) {
            $prs = $this->github->listPullRequests($repo, $branchName, 'open');

            foreach ($prs as $pr) {
                $this->github->mergePullRequest($repo, $pr['number']);
            }
        }
    }

    /**
     * Transition story to merged after all PRs are merged.
     */
    public function markMerged(Story $story): Story
    {
        $story->transitionTo('merged');

        return $story;
    }

    /**
     * Escalate major version or infra changes — creates HITL Release Approval escalation.
     */
    public function escalateReleaseApproval(Story $story, Agent $agent, string $reason): HitlEscalation
    {
        return HitlEscalation::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'risk',
            'trigger_class' => 'release_approval',
            'reason' => $reason,
        ]);
    }

    /**
     * Deploy the story after merge.
     */
    public function deploy(Story $story): Story
    {
        $story->transitionTo('deployed');

        return $story;
    }

    /**
     * Clean up branches after deployment.
     */
    public function cleanup(Story $story, array $repos): void
    {
        $branchName = 'feature/story-'.$story->id;

        foreach ($repos as $repo) {
            $this->github->deleteBranch($repo, $branchName);
        }
    }

    /**
     * Close the story after deployment and cleanup.
     */
    public function closeStory(Story $story): Story
    {
        $story->transitionTo('closed_done');

        return $story;
    }
}
