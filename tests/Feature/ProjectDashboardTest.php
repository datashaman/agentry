<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('projects.show', $project));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the project dashboard', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
});

test('project dashboard displays project name', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'My Dashboard Project']);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('My Dashboard Project');
});

test('project dashboard displays breadcrumbs', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Corp']);
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Widget App']);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('Acme Corp');
    $response->assertSee('Projects');
    $response->assertSee('Widget App');
});
