<?php

use App\Models\Agent;
use App\Models\Epic;
use App\Models\Milestone;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\Team;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('projects.stories.index', $project));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the stories page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', $project));
    $response->assertOk();
});

test('stories page displays stories for the project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Implement Login']);
    Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Add Dashboard']);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', $project));
    $response->assertOk();
    $response->assertSee('Implement Login');
    $response->assertSee('Add Dashboard');
});

test('stories page does not display stories from other projects', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $otherProject = Project::factory()->create(['organization_id' => $organization->id]);
    $otherEpic = Epic::factory()->create(['project_id' => $otherProject->id]);
    Story::factory()->create(['epic_id' => $otherEpic->id, 'title' => 'Secret Story']);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', $project));
    $response->assertOk();
    $response->assertDontSee('Secret Story');
});

test('stories page shows story status, priority, and points', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    Story::factory()->create([
        'epic_id' => $epic->id,
        'title' => 'Test Story',
        'status' => 'in_development',
        'priority' => 2,
        'story_points' => 5,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', $project));
    $response->assertOk();
    $response->assertSee('Test Story');
    $response->assertSee('in development');
    $response->assertSee('P2');
});

test('stories page shows assigned agent name', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Code Bot']);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    Story::factory()->create([
        'epic_id' => $epic->id,
        'title' => 'Agent Story',
        'assigned_agent_id' => $agent->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', $project));
    $response->assertOk();
    $response->assertSee('Code Bot');
});

test('stories page shows epic and milestone names', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id, 'title' => 'Auth Epic']);
    $milestone = Milestone::factory()->create(['project_id' => $project->id, 'title' => 'Sprint 1']);
    Story::factory()->create([
        'epic_id' => $epic->id,
        'milestone_id' => $milestone->id,
        'title' => 'Milestone Story',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', $project));
    $response->assertOk();
    $response->assertSee('Auth Epic');
    $response->assertSee('Sprint 1');
});

test('stories page filters by status', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Backlog Story', 'status' => 'backlog']);
    Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Dev Story', 'status' => 'in_development']);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', [$project, 'status' => 'backlog']));
    $response->assertOk();
    $response->assertSee('Backlog Story');
    $response->assertDontSee('Dev Story');
});

test('stories page filters by epic', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic1 = Epic::factory()->create(['project_id' => $project->id, 'title' => 'Epic One']);
    $epic2 = Epic::factory()->create(['project_id' => $project->id, 'title' => 'Epic Two']);
    Story::factory()->create(['epic_id' => $epic1->id, 'title' => 'Story A']);
    Story::factory()->create(['epic_id' => $epic2->id, 'title' => 'Story B']);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', [$project, 'epic' => $epic1->id]));
    $response->assertOk();
    $response->assertSee('Story A');
    $response->assertDontSee('Story B');
});

test('stories page filters by milestone', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id, 'title' => 'Sprint 1']);
    Story::factory()->create(['epic_id' => $epic->id, 'milestone_id' => $milestone->id, 'title' => 'Sprint Story']);
    Story::factory()->create(['epic_id' => $epic->id, 'title' => 'No Sprint Story']);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', [$project, 'milestone' => $milestone->id]));
    $response->assertOk();
    $response->assertSee('Sprint Story');
    $response->assertDontSee('No Sprint Story');
});

test('stories page sorts by priority by default', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    Story::factory()->create(['epic_id' => $epic->id, 'title' => 'Low Priority', 'priority' => 10]);
    Story::factory()->create(['epic_id' => $epic->id, 'title' => 'High Priority', 'priority' => 1]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', $project));
    $response->assertOk();
    $response->assertSeeInOrder(['High Priority', 'Low Priority']);
});

test('stories page links to story detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', $project));
    $response->assertOk();
    $response->assertSee(route('projects.stories.show', [$project, $story]));
});

test('stories page shows no stories message when none exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.index', $project));
    $response->assertOk();
    $response->assertSee('No Stories');
});
