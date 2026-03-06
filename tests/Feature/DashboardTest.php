<?php

use App\Models\Organization;
use App\Models\Project;
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
