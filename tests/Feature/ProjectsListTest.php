<?php

use App\Models\Bug;
use App\Models\Epic;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('projects.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the projects page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('projects.index'));
    $response->assertOk();
});

test('projects page displays projects for current organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Alpha Project']);
    Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Beta Project']);

    $this->actingAs($user);

    $response = $this->get(route('projects.index'));
    $response->assertOk();
    $response->assertSee('Alpha Project');
    $response->assertSee('Beta Project');
});

test('projects page does not display projects from other organizations', function () {
    $organization = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    Project::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Secret Project']);

    $this->actingAs($user);

    $response = $this->get(route('projects.index'));
    $response->assertOk();
    $response->assertDontSee('Secret Project');
});

test('projects page shows story count for each project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Counted Project']);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    Story::factory()->count(3)->create(['epic_id' => $epic->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.index'));
    $response->assertOk();
    $response->assertSee('Counted Project');
    $response->assertSeeInOrder(['Counted Project', '3']);
});

test('projects page shows bug count for each project', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Buggy Project']);
    Bug::factory()->count(2)->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.index'));
    $response->assertOk();
    $response->assertSee('Buggy Project');
    $response->assertSeeInOrder(['Buggy Project', '2']);
});

test('projects page shows project slug', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    Project::factory()->create(['organization_id' => $organization->id, 'name' => 'My Project', 'slug' => 'my-project']);

    $this->actingAs($user);

    $response = $this->get(route('projects.index'));
    $response->assertOk();
    $response->assertSee('my-project');
});

test('projects page links to project detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.index'));
    $response->assertOk();
    $response->assertSee(route('projects.show', $project));
});

test('projects page shows no organization message when user has none', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('projects.index'));
    $response->assertOk();
    $response->assertSee('No Organization');
});

test('projects page shows no projects message when organization has none', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('projects.index'));
    $response->assertOk();
    $response->assertSee('No Projects');
});
