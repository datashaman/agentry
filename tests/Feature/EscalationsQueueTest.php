<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Epic;
use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\Team;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('escalations.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the escalations page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
});

test('escalations page displays unresolved escalations for the user organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'trigger_type' => 'confidence',
        'reason' => 'Low confidence on design',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('Low confidence on design');
    $response->assertSee('Confidence');
});

test('escalations page does not display resolved escalations', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    HitlEscalation::factory()->resolved()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'reason' => 'Already resolved issue',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertDontSee('Already resolved issue');
});

test('escalations page does not display escalations from other organizations', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $otherProject = Project::factory()->create(['organization_id' => $otherOrganization->id]);
    $otherEpic = Epic::factory()->create(['project_id' => $otherProject->id]);
    $otherStory = Story::factory()->create(['epic_id' => $otherEpic->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $otherStory->id,
        'work_item_type' => Story::class,
        'reason' => 'Secret escalation',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertDontSee('Secret escalation');
});

test('escalations page shows escalations for bugs', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'trigger_type' => 'risk',
        'reason' => 'High risk bug fix',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('High risk bug fix');
    $response->assertSee('Bug');
});

test('escalations page shows escalations for ops requests', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $opsRequest = OpsRequest::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'trigger_type' => 'policy',
        'reason' => 'Policy violation on deploy',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('Policy violation on deploy');
    $response->assertSee('Ops Request');
});

test('escalations page can filter by trigger type', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'trigger_type' => 'confidence',
        'reason' => 'Confidence issue here',
    ]);

    HitlEscalation::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'trigger_type' => 'risk',
        'reason' => 'Risk issue here',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index', ['triggerType' => 'confidence']));
    $response->assertOk();
    $response->assertSee('Confidence issue here');
    $response->assertDontSee('Risk issue here');
});

test('escalations page can filter by work item type', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'reason' => 'Story escalation',
    ]);

    HitlEscalation::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'reason' => 'Bug escalation',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index', ['workItemType' => 'bug']));
    $response->assertOk();
    $response->assertSee('Bug escalation');
    $response->assertDontSee('Story escalation');
});

test('escalations page displays agent name', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Reviewer Bot']);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    HitlEscalation::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'raised_by_agent_id' => $agent->id,
        'reason' => 'Agent raised this',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('Reviewer Bot');
});

test('escalations page shows work item links', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Linked Story Title']);

    HitlEscalation::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'reason' => 'Needs review',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('Linked Story Title');
    $response->assertSee(route('projects.stories.show', [$project, $story]));
});

test('escalations page shows empty state when no escalations', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('No Escalations');
});
