<?php

use App\Models\Organization;
use App\Models\User;

test('import page renders for authenticated user', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('skills.import'));
    $response->assertOk();
    $response->assertSee('Import Skills from Repo');
});

test('import page requires authentication', function () {
    $response = $this->get(route('skills.import'));
    $response->assertRedirect();
});

test('skills index has import button', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create(['current_organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('skills.index'));
    $response->assertOk();
    $response->assertSee('Import from Repo');
});
