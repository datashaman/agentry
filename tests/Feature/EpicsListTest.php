<?php

use App\Models\Epic;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('projects.epics.index', $project));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the epics page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('projects.epics.index', $project));
    $response->assertOk();
});

test('epics page displays epics for the project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Epic::factory()->create(['project_id' => $project->id, 'title' => 'User Authentication']);
    Epic::factory()->create(['project_id' => $project->id, 'title' => 'Payment System']);

    $this->actingAs($user);

    $response = $this->get(route('projects.epics.index', $project));
    $response->assertOk();
    $response->assertSee('User Authentication');
    $response->assertSee('Payment System');
});

test('epics page does not display epics from other projects', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $otherProject = Project::factory()->create(['organization_id' => $organization->id]);
    Epic::factory()->create(['project_id' => $otherProject->id, 'title' => 'Secret Epic']);

    $this->actingAs($user);

    $response = $this->get(route('projects.epics.index', $project));
    $response->assertOk();
    $response->assertDontSee('Secret Epic');
});

test('epics page shows epic status and priority', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Epic::factory()->create([
        'project_id' => $project->id,
        'title' => 'Core Features',
        'status' => 'open',
        'priority' => 3,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.epics.index', $project));
    $response->assertOk();
    $response->assertSee('Core Features');
    $response->assertSee('open');
    $response->assertSee('P3');
});

test('epics page shows story count per epic', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id, 'title' => 'Big Epic']);
    Story::factory()->count(3)->create(['epic_id' => $epic->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.epics.index', $project));
    $response->assertOk();
    $response->assertSee('3 stories');
});

test('epics page shows singular story for count of one', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id, 'title' => 'Small Epic']);
    Story::factory()->create(['epic_id' => $epic->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.epics.index', $project));
    $response->assertOk();
    $response->assertSee('1 story');
});

test('epics page shows empty state when no epics', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.epics.index', $project));
    $response->assertOk();
    $response->assertSee('No Epics');
});

test('epics page links to stories filtered by epic', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id, 'title' => 'Linked Epic']);

    $this->actingAs($user);

    $response = $this->get(route('projects.epics.index', $project));
    $response->assertOk();
    $expectedUrl = route('projects.stories.index', ['project' => $project, 'epic' => $epic->id]);
    $response->assertSee($expectedUrl, false);
});

test('epics are ordered by priority', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Epic::factory()->create(['project_id' => $project->id, 'title' => 'Low Priority', 'priority' => 5]);
    Epic::factory()->create(['project_id' => $project->id, 'title' => 'High Priority', 'priority' => 1]);

    $this->actingAs($user);

    $response = $this->get(route('projects.epics.index', $project));
    $response->assertOk();
    $response->assertSeeInOrder(['High Priority', 'Low Priority']);
});
