<?php

use App\Models\ActionLog;
use App\Models\Bug;
use App\Models\Epic;
use App\Models\HitlEscalation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard displays organization name', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Corp']);
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Acme Corp');
});

test('dashboard shows active stories count by status', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);

    Story::factory()->create(['epic_id' => $epic->id, 'status' => 'in_development']);
    Story::factory()->create(['epic_id' => $epic->id, 'status' => 'in_development']);
    Story::factory()->create(['epic_id' => $epic->id, 'status' => 'in_review']);
    Story::factory()->create(['epic_id' => $epic->id, 'status' => 'backlog']);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('In development');
    $response->assertSee('In review');
});

test('dashboard shows open bugs count', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    Bug::factory()->count(3)->create(['project_id' => $project->id, 'status' => 'new']);
    Bug::factory()->create(['project_id' => $project->id, 'status' => 'closed_fixed']);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSeeInOrder(['Open Bugs', '3']);
});

test('dashboard shows unresolved escalations count', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    HitlEscalation::factory()->forStory($story)->create();
    HitlEscalation::factory()->forStory($story)->create();
    HitlEscalation::factory()->forStory($story)->resolved()->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSeeInOrder(['Unresolved Escalations', '2']);
});

test('dashboard shows recent activity log', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    ActionLog::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'action' => 'committed_code',
        'reasoning' => 'Implemented login feature',
        'timestamp' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Recent Activity');
    $response->assertSee('committed code');
    $response->assertSee('Implemented login feature');
});

test('dashboard shows projects for organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Project Alpha']);
    Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Project Beta']);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Project Alpha');
    $response->assertSee('Project Beta');
});

test('dashboard shows no organization message when user has none', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('No Organization');
});

test('dashboard does not show data from other organizations', function () {
    $organization = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $otherProject = Project::factory()->create(['organization_id' => $otherOrg->id]);
    Bug::factory()->count(5)->create(['project_id' => $otherProject->id, 'status' => 'new']);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSeeInOrder(['Open Bugs', '0']);
});
