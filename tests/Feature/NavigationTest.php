<?php

use App\Models\Organization;
use App\Models\User;

test('authenticated user sees sidebar navigation with Dashboard, Projects, and Escalations', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Projects');
    $response->assertSee('Escalations');
});

test('sidebar shows organization context for single-org user', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Corp']);
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Acme Corp');
});

test('sidebar shows organization switcher for multi-org user', function () {
    $org1 = Organization::factory()->create(['name' => 'Alpha Org']);
    $org2 = Organization::factory()->create(['name' => 'Beta Org']);
    $user = User::factory()->create();
    $user->organizations()->attach([$org1->id, $org2->id]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Alpha Org');
    $response->assertSee('Beta Org');
});

test('projects navigation link is accessible', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('projects.index'));
    $response->assertOk();
    $response->assertSee('Projects');
});

test('escalations navigation link is accessible', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('escalations.index'));
    $response->assertOk();
    $response->assertSee('Escalations');
});

test('breadcrumbs show organization context on dashboard', function () {
    $organization = Organization::factory()->create(['name' => 'Test Org']);
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Test Org');
});

test('guests cannot access navigation pages', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
    $this->get(route('escalations.index'))->assertRedirect(route('login'));
});
