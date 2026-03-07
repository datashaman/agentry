<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('work-items.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the work items page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('work-items.index'));
    $response->assertOk();
});

test('work items page displays work items for the user organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    WorkItem::factory()->create([
        'project_id' => $project->id,
        'title' => 'Fix login bug',
        'provider_key' => 'PROJ-123',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('work-items.index'));
    $response->assertOk();
    $response->assertSee('Fix login bug');
    $response->assertSee('PROJ-123');
});

test('work items page does not display items from other organizations', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $otherProject = Project::factory()->create(['organization_id' => $otherOrganization->id]);

    WorkItem::factory()->create([
        'project_id' => $otherProject->id,
        'title' => 'Secret work item',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('work-items.index'));
    $response->assertOk();
    $response->assertDontSee('Secret work item');
});

test('work items page can filter by project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project1 = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Project Alpha']);
    $project2 = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Project Beta']);

    WorkItem::factory()->create(['project_id' => $project1->id, 'title' => 'Alpha task']);
    WorkItem::factory()->create(['project_id' => $project2->id, 'title' => 'Beta task']);

    $this->actingAs($user);

    $response = $this->get(route('work-items.index', ['projectId' => $project1->id]));
    $response->assertOk();
    $response->assertSee('Alpha task');
    $response->assertDontSee('Beta task');
});

test('work items page can filter by status', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    WorkItem::factory()->create(['project_id' => $project->id, 'title' => 'Open item', 'status' => 'open']);
    WorkItem::factory()->create(['project_id' => $project->id, 'title' => 'Closed item', 'status' => 'closed']);

    $this->actingAs($user);

    $response = $this->get(route('work-items.index', ['status' => 'open']));
    $response->assertOk();
    $response->assertSee('Open item');
    $response->assertDontSee('Closed item');
});

test('work items page can search by title', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    WorkItem::factory()->create(['project_id' => $project->id, 'title' => 'Fix authentication']);
    WorkItem::factory()->create(['project_id' => $project->id, 'title' => 'Update dashboard']);

    $this->actingAs($user);

    $response = $this->get(route('work-items.index', ['search' => 'authentication']));
    $response->assertOk();
    $response->assertSee('Fix authentication');
    $response->assertDontSee('Update dashboard');
});

test('work items page shows empty state when no items', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('work-items.index'));
    $response->assertOk();
    $response->assertSee('No Work Items');
});

test('work items page shows project name and link', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'My Project']);

    WorkItem::factory()->create(['project_id' => $project->id, 'title' => 'Some task']);

    $this->actingAs($user);

    $response = $this->get(route('work-items.index'));
    $response->assertOk();
    $response->assertSee('My Project');
    $response->assertSee(route('projects.show', $project));
});

test('work items page displays classified type badge', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    WorkItem::factory()->create([
        'project_id' => $project->id,
        'title' => 'Classified item',
        'classified_type' => 'Bug',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('work-items.index'));
    $response->assertOk();
    $response->assertSee('Bug');
});
