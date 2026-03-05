<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

test('user can switch organization and current_organization_id is updated', function () {
    $org1 = Organization::factory()->create(['name' => 'Alpha Org']);
    $org2 = Organization::factory()->create(['name' => 'Beta Org']);
    $user = User::factory()->create();
    $user->organizations()->attach([$org1->id, $org2->id]);

    $this->actingAs($user);

    $response = $this->post(route('switch-organization', $org2));

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->current_organization_id)->toBe($org2->id);
});

test('user cannot switch to an organization they do not belong to', function () {
    $org1 = Organization::factory()->create(['name' => 'Alpha Org']);
    $orgOther = Organization::factory()->create(['name' => 'Other Org']);
    $user = User::factory()->withOrganization($org1)->create();

    $this->actingAs($user);

    $this->post(route('switch-organization', $orgOther));

    expect($user->fresh()->current_organization_id)->toBeNull();
});

test('after switching org, dashboard shows data for the new org', function () {
    $org1 = Organization::factory()->create(['name' => 'Alpha Org']);
    $org2 = Organization::factory()->create(['name' => 'Beta Org']);
    $user = User::factory()->create();
    $user->organizations()->attach([$org1->id, $org2->id]);

    Project::factory()->create(['organization_id' => $org1->id, 'name' => 'Alpha Project']);
    Project::factory()->create(['organization_id' => $org2->id, 'name' => 'Beta Project']);

    $this->actingAs($user);

    // Before switch, user sees first org (Alpha)
    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Alpha Org');

    // Switch to Beta Org
    $this->post(route('switch-organization', $org2));

    // After switch, dashboard shows Beta Org data
    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Beta Org');
    $response->assertSee('Beta Project');
});

test('currentOrganization returns stored org when current_organization_id is set', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user = User::factory()->create();
    $user->organizations()->attach([$org1->id, $org2->id]);

    $user->switchOrganization($org2);

    expect($user->fresh()->currentOrganization()->id)->toBe($org2->id);
});

test('currentOrganization falls back to first org when current_organization_id is null', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->withOrganization($org)->create();

    expect($user->currentOrganization()->id)->toBe($org->id);
    expect($user->current_organization_id)->toBeNull();
});

test('organization switcher dropdown is visible for multi-org user', function () {
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

test('guests cannot switch organizations', function () {
    $org = Organization::factory()->create();

    $this->post(route('switch-organization', $org))
        ->assertRedirect(route('login'));
});
