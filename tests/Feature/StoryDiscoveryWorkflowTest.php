<?php

use App\Models\Agent;
use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\Story;
use App\Services\StoryDiscoveryWorkflow;

// --- Enter Spec Critique ---

test('enter spec critique transitions story to spec_critique and creates spec critique', function () {
    $story = Story::factory()->create(['status' => 'backlog']);
    $agent = Agent::factory()->create();
    $workflow = new StoryDiscoveryWorkflow;

    $critique = $workflow->enterSpecCritique($story, $agent);

    expect($story->fresh()->status)->toBe('spec_critique')
        ->and($story->fresh()->spec_revision_count)->toBe(1)
        ->and($critique)->toBeInstanceOf(Critique::class)
        ->and($critique->critic_type)->toBe('spec')
        ->and($critique->work_item_id)->toBe($story->id)
        ->and($critique->work_item_type)->toBe(Story::class)
        ->and($critique->agent_id)->toBe($agent->id)
        ->and($critique->revision)->toBe(1)
        ->and($critique->disposition)->toBe('pending');
});

test('enter spec critique increments spec_revision_count', function () {
    $story = Story::factory()->create(['status' => 'backlog', 'spec_revision_count' => 2]);
    $agent = Agent::factory()->create();
    $workflow = new StoryDiscoveryWorkflow;

    $critique = $workflow->enterSpecCritique($story, $agent);

    expect($story->fresh()->spec_revision_count)->toBe(3)
        ->and($critique->revision)->toBe(3);
});

// --- Approve Grooming ---

test('approve grooming transitions story to refined and accepts critique', function () {
    $story = Story::factory()->create(['status' => 'spec_critique']);
    $agent = Agent::factory()->create();
    $critique = Critique::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'agent_id' => $agent->id,
        'critic_type' => 'spec',
        'disposition' => 'pending',
    ]);
    $workflow = new StoryDiscoveryWorkflow;

    $result = $workflow->approveGrooming($story, $critique);

    expect($result->fresh()->status)->toBe('refined')
        ->and($critique->fresh()->disposition)->toBe('accepted');
});

// --- Reject Grooming ---

test('reject grooming transitions story to backlog and rejects critique', function () {
    $story = Story::factory()->create(['status' => 'spec_critique']);
    $agent = Agent::factory()->create();
    $critique = Critique::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'agent_id' => $agent->id,
        'critic_type' => 'spec',
        'disposition' => 'pending',
    ]);
    $workflow = new StoryDiscoveryWorkflow;

    $result = $workflow->rejectGrooming($story, $critique);

    expect($result->fresh()->status)->toBe('backlog')
        ->and($critique->fresh()->disposition)->toBe('rejected');
});

// --- Minor Revision ---

test('request minor revision creates new critique superseding the previous one', function () {
    $story = Story::factory()->create(['status' => 'spec_critique', 'spec_revision_count' => 1]);
    $agent = Agent::factory()->create();
    $critique = Critique::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'agent_id' => $agent->id,
        'critic_type' => 'spec',
        'revision' => 1,
        'disposition' => 'pending',
    ]);
    $workflow = new StoryDiscoveryWorkflow;

    $newCritique = $workflow->requestMinorRevision($story, $critique, $agent);

    expect($critique->fresh()->disposition)->toBe('accepted')
        ->and($newCritique->critic_type)->toBe('spec')
        ->and($newCritique->revision)->toBe(2)
        ->and($newCritique->supersedes_id)->toBe($critique->id)
        ->and($newCritique->disposition)->toBe('pending')
        ->and($story->fresh()->spec_revision_count)->toBe(2)
        ->and($story->fresh()->status)->toBe('spec_critique');
});

test('minor revision does not set substantial_change flag', function () {
    $story = Story::factory()->create(['status' => 'spec_critique', 'substantial_change' => false]);
    $agent = Agent::factory()->create();
    $critique = Critique::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'disposition' => 'pending',
    ]);
    $workflow = new StoryDiscoveryWorkflow;

    $workflow->requestMinorRevision($story, $critique, $agent);

    expect($story->fresh()->substantial_change)->toBeFalse();
});

// --- Substantial Revision ---

test('substantial revision sets substantial_change flag and creates new critique', function () {
    $story = Story::factory()->create(['status' => 'spec_critique', 'spec_revision_count' => 1, 'substantial_change' => false]);
    $agent = Agent::factory()->create();
    $critique = Critique::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'agent_id' => $agent->id,
        'critic_type' => 'spec',
        'revision' => 1,
        'disposition' => 'pending',
    ]);
    $workflow = new StoryDiscoveryWorkflow;

    $newCritique = $workflow->requestSubstantialRevision($story, $critique, $agent);

    expect($story->fresh()->substantial_change)->toBeTrue()
        ->and($critique->fresh()->disposition)->toBe('accepted')
        ->and($newCritique->critic_type)->toBe('spec')
        ->and($newCritique->revision)->toBe(2)
        ->and($newCritique->supersedes_id)->toBe($critique->id)
        ->and($newCritique->disposition)->toBe('pending')
        ->and($story->fresh()->spec_revision_count)->toBe(2);
});

// --- Cross-Team Impact Escalation ---

test('cross-team impact triggers HITL scope review escalation', function () {
    $story = Story::factory()->create(['status' => 'spec_critique']);
    $agent = Agent::factory()->create();
    $workflow = new StoryDiscoveryWorkflow;

    $escalation = $workflow->escalateCrossTeamImpact($story, $agent);

    expect($escalation)->toBeInstanceOf(HitlEscalation::class)
        ->and($escalation->work_item_id)->toBe($story->id)
        ->and($escalation->work_item_type)->toBe(Story::class)
        ->and($escalation->raised_by_agent_id)->toBe($agent->id)
        ->and($escalation->trigger_type)->toBe('policy')
        ->and($escalation->trigger_class)->toBe('scope_review')
        ->and($escalation->resolved_at)->toBeNull();
});

test('cross-team impact escalation blocks story from progressing', function () {
    $story = Story::factory()->create(['status' => 'spec_critique']);
    $agent = Agent::factory()->create();
    $workflow = new StoryDiscoveryWorkflow;

    $workflow->escalateCrossTeamImpact($story, $agent);

    expect($story->hasUnresolvedEscalation())->toBeTrue();
});

// --- Full Discovery Phase Flow ---

test('full discovery flow: backlog -> spec_critique -> approve -> refined', function () {
    $story = Story::factory()->create(['status' => 'backlog']);
    $agent = Agent::factory()->create();
    $workflow = new StoryDiscoveryWorkflow;

    $critique = $workflow->enterSpecCritique($story, $agent);
    $workflow->approveGrooming($story, $critique);

    expect($story->fresh()->status)->toBe('refined')
        ->and($story->fresh()->spec_revision_count)->toBe(1)
        ->and($critique->fresh()->disposition)->toBe('accepted');
});

test('full discovery flow with minor revision: critique -> minor revision -> approve', function () {
    $story = Story::factory()->create(['status' => 'backlog']);
    $agent = Agent::factory()->create();
    $workflow = new StoryDiscoveryWorkflow;

    $critique1 = $workflow->enterSpecCritique($story, $agent);
    $critique2 = $workflow->requestMinorRevision($story, $critique1, $agent);
    $workflow->approveGrooming($story, $critique2);

    expect($story->fresh()->status)->toBe('refined')
        ->and($story->fresh()->spec_revision_count)->toBe(2)
        ->and($critique1->fresh()->disposition)->toBe('accepted')
        ->and($critique2->fresh()->disposition)->toBe('accepted')
        ->and($critique2->supersedes_id)->toBe($critique1->id);
});

test('full discovery flow with substantial revision: critique -> substantial -> approve', function () {
    $story = Story::factory()->create(['status' => 'backlog']);
    $agent = Agent::factory()->create();
    $workflow = new StoryDiscoveryWorkflow;

    $critique1 = $workflow->enterSpecCritique($story, $agent);
    $critique2 = $workflow->requestSubstantialRevision($story, $critique1, $agent);
    $workflow->approveGrooming($story, $critique2);

    expect($story->fresh()->status)->toBe('refined')
        ->and($story->fresh()->substantial_change)->toBeTrue()
        ->and($story->fresh()->spec_revision_count)->toBe(2)
        ->and($critique1->fresh()->disposition)->toBe('accepted')
        ->and($critique2->fresh()->disposition)->toBe('accepted');
});

test('full discovery flow with rejection: critique -> reject -> back to backlog', function () {
    $story = Story::factory()->create(['status' => 'backlog']);
    $agent = Agent::factory()->create();
    $workflow = new StoryDiscoveryWorkflow;

    $critique = $workflow->enterSpecCritique($story, $agent);
    $workflow->rejectGrooming($story, $critique);

    expect($story->fresh()->status)->toBe('backlog')
        ->and($critique->fresh()->disposition)->toBe('rejected');
});

test('story can re-enter spec critique after rejection', function () {
    $story = Story::factory()->create(['status' => 'backlog']);
    $agent = Agent::factory()->create();
    $workflow = new StoryDiscoveryWorkflow;

    $critique1 = $workflow->enterSpecCritique($story, $agent);
    $workflow->rejectGrooming($story, $critique1);

    expect($story->fresh()->status)->toBe('backlog');

    $critique2 = $workflow->enterSpecCritique($story, $agent);
    $workflow->approveGrooming($story, $critique2);

    expect($story->fresh()->status)->toBe('refined')
        ->and($story->fresh()->spec_revision_count)->toBe(2);
});

// --- State machine integration ---

test('spec_critique to refined transition works in state machine', function () {
    $story = Story::factory()->create(['status' => 'spec_critique']);

    $story->transitionTo('refined');

    expect($story->fresh()->status)->toBe('refined');
});

test('spec_critique to backlog transition works in state machine', function () {
    $story = Story::factory()->create(['status' => 'spec_critique']);

    $story->transitionTo('backlog');

    expect($story->fresh()->status)->toBe('backlog');
});

test('backlog to spec_critique transition works in state machine', function () {
    $story = Story::factory()->create(['status' => 'backlog']);

    $story->transitionTo('spec_critique');

    expect($story->fresh()->status)->toBe('spec_critique');
});
