<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

test('project sub-nav appears on project overview page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('data-test="project-sub-nav"', false);
});

test('project sub-nav contains all section links', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('Overview');
    $response->assertSee('Work Items');
    $response->assertSee('Ops Requests');
    $response->assertSee('Repos');

    $response->assertSee('Action Logs');
});

test('project sub-nav shows Overview as active on project overview page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertSee('data-test="sub-nav-overview"', false);
    $response->assertSee('bg-zinc-100', false);
});

test('project sub-nav does not appear on dashboard without project context', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertDontSee('data-test="project-sub-nav"', false);
});
