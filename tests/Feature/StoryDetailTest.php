<?php

use App\Models\Agent;
use App\Models\Critique;
use App\Models\Dependency;
use App\Models\Epic;
use App\Models\HitlEscalation;
use App\Models\Milestone;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the story detail page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
});

test('story detail page displays story header with title, status, priority, points, due date, and agent', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Dev Bot']);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create([
        'epic_id' => $epic->id,
        'title' => 'Implement Authentication',
        'status' => 'in_development',
        'priority' => 2,
        'story_points' => 8,
        'due_date' => '2026-04-15',
        'assigned_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Implement Authentication');
    $response->assertSee('in development');
    $response->assertSee('P2');
    $response->assertSee('8 points');
    $response->assertSee('Apr 15, 2026');
    $response->assertSee('Dev Bot');
});

test('story detail page displays description', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create([
        'epic_id' => $epic->id,
        'description' => 'This story implements the full authentication flow.',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('This story implements the full authentication flow.');
});

test('story detail page displays epic and milestone', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id, 'title' => 'Auth Epic']);
    $milestone = Milestone::factory()->create(['project_id' => $project->id, 'title' => 'Sprint 3']);
    $story = Story::factory()->create([
        'epic_id' => $epic->id,
        'milestone_id' => $milestone->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Auth Epic');
    $response->assertSee('Sprint 3');
});

test('story detail page displays critiques with type, severity, disposition, and summary', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    Critique::factory()->forStory($story)->create([
        'critic_type' => 'spec',
        'severity' => 'blocking',
        'disposition' => 'pending',
        'revision' => 2,
        'issues' => ['Missing error handling'],
        'questions' => ['What about edge cases?'],
        'recommendations' => ['Add retry logic'],
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Critiques');
    $response->assertSee('spec');
    $response->assertSee('blocking');
    $response->assertSee('pending');
    $response->assertSee('Rev 2');
    $response->assertSee('Missing error handling');
    $response->assertSee('What about edge cases?');
    $response->assertSee('Add retry logic');
});

test('story detail page displays tasks and subtasks with status', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $task = Task::factory()->create([
        'story_id' => $story->id,
        'title' => 'Write unit tests',
        'status' => 'in_progress',
        'type' => 'test',
    ]);

    Subtask::factory()->create([
        'task_id' => $task->id,
        'title' => 'Test login flow',
        'status' => 'completed',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Tasks');
    $response->assertSee('Write unit tests');
    $response->assertSee('in_progress');
    $response->assertSee('Test login flow');
    $response->assertSee('completed');
});

test('story detail page displays HITL escalations (resolved and unresolved)', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Review Bot']);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    HitlEscalation::factory()->forStory($story)->create([
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'confidence',
        'reason' => 'Low confidence in spec interpretation',
    ]);

    HitlEscalation::factory()->forStory($story)->resolved()->create([
        'raised_by_agent_id' => $agent->id,
        'trigger_type' => 'risk',
        'reason' => 'High risk deployment',
        'resolution' => 'Approved after review',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('HITL Escalations');
    $response->assertSee('confidence');
    $response->assertSee('Low confidence in spec interpretation');
    $response->assertSee('Unresolved');
    $response->assertSee('risk');
    $response->assertSee('High risk deployment');
    $response->assertSee('Resolved');
    $response->assertSee('Approved after review');
    $response->assertSee('Review Bot');
});

test('story detail page displays dependencies', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $blockerStory = Story::factory()->create([
        'epic_id' => $epic->id,
        'title' => 'Setup Database Schema',
    ]);

    Dependency::factory()->create([
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
        'blocker_type' => Story::class,
        'blocker_id' => $blockerStory->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Dependencies');
    $response->assertSee('Setup Database Schema');
});

test('story detail page shows breadcrumbs with organization and project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee($organization->name);
    $response->assertSee($project->name);
});
