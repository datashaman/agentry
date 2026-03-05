<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\Story;

class StoryDiscoveryWorkflow
{
    /**
     * Move a story into spec critique, creating a Critique record of type "spec".
     */
    public function enterSpecCritique(Story $story, Agent $specCriticAgent): Critique
    {
        $story->transitionTo('spec_critique');

        $story->increment('spec_revision_count');

        return Critique::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'agent_id' => $specCriticAgent->id,
            'critic_type' => 'spec',
            'revision' => $story->spec_revision_count,
            'severity' => 'suggestion',
            'disposition' => 'pending',
        ]);
    }

    /**
     * Approve the story during grooming, transitioning it to refined.
     */
    public function approveGrooming(Story $story, Critique $critique): Story
    {
        $critique->update(['disposition' => 'accepted']);

        $story->transitionTo('refined');

        return $story;
    }

    /**
     * Reject the story during grooming, returning it to backlog.
     */
    public function rejectGrooming(Story $story, Critique $critique): Story
    {
        $critique->update(['disposition' => 'rejected']);

        $story->transitionTo('backlog');

        return $story;
    }

    /**
     * Request a minor revision — critique is accepted but story stays in spec_critique for another pass.
     */
    public function requestMinorRevision(Story $story, Critique $critique, Agent $specCriticAgent): Critique
    {
        $critique->update(['disposition' => 'accepted']);

        $story->increment('spec_revision_count');

        return Critique::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'agent_id' => $specCriticAgent->id,
            'critic_type' => 'spec',
            'revision' => $story->spec_revision_count,
            'supersedes_id' => $critique->id,
            'severity' => 'suggestion',
            'disposition' => 'pending',
        ]);
    }

    /**
     * Request a substantial revision — sets substantial_change flag and triggers re-critique.
     */
    public function requestSubstantialRevision(Story $story, Critique $critique, Agent $specCriticAgent): Critique
    {
        $critique->update(['disposition' => 'accepted']);

        $story->update(['substantial_change' => true]);
        $story->increment('spec_revision_count');

        return Critique::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'agent_id' => $specCriticAgent->id,
            'critic_type' => 'spec',
            'revision' => $story->spec_revision_count,
            'supersedes_id' => $critique->id,
            'severity' => 'suggestion',
            'disposition' => 'pending',
        ]);
    }

    /**
     * Escalate a story for cross-team impact — creates a HITL Scope Review escalation.
     */
    public function escalateCrossTeamImpact(Story $story, Agent $agent): HitlEscalation
    {
        return HitlEscalation::create([
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'raised_by_agent_id' => $agent->id,
            'trigger_type' => 'policy',
            'trigger_class' => 'scope_review',
            'reason' => 'Cross-team impact detected during discovery phase.',
        ]);
    }
}
