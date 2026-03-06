<?php

use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

test('unauthenticated user cannot access agent permissions page', function () {
    $this->get(route('agent-permissions.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can access agent permissions page', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get(route('agent-permissions.index'))
        ->assertOk()
        ->assertSee('Agent Permissions');
});

test('agent permissions page displays all permission categories', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user);

    $response = $this->get(route('agent-permissions.index'));
    $response->assertOk();

    foreach (array_keys(Organization::AGENT_PERMISSIONS) as $category) {
        $response->assertSee($category);
    }
});

test('all permissions default to false for new organization', function () {
    $organization = Organization::factory()->create();

    foreach (Organization::allAgentPermissionKeys() as $key) {
        expect($organization->agentCan($key))->toBeFalse();
    }
});

test('agentCan returns true for enabled permission', function () {
    $organization = Organization::factory()->create([
        'agent_permissions' => ['create_pull_requests' => true, 'push_code' => true],
    ]);

    expect($organization->agentCan('create_pull_requests'))->toBeTrue();
    expect($organization->agentCan('push_code'))->toBeTrue();
    expect($organization->agentCan('merge_pull_requests'))->toBeFalse();
});

test('save permissions persists to database', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $permissions = [];
    foreach (Organization::allAgentPermissionKeys() as $key) {
        $permissions[$key] = false;
    }
    $permissions['create_branches'] = true;
    $permissions['push_code'] = true;

    Livewire::test('pages::agent-permissions.index')
        ->set('permissions', $permissions)
        ->call('save');

    $organization->refresh();
    expect($organization->agentCan('create_branches'))->toBeTrue();
    expect($organization->agentCan('push_code'))->toBeTrue();
    expect($organization->agentCan('merge_pull_requests'))->toBeFalse();
});

test('permissions page loads with existing saved permissions', function () {
    $organization = Organization::factory()->create([
        'agent_permissions' => ['create_pull_requests' => true, 'create_bugs' => true],
    ]);
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    Livewire::test('pages::agent-permissions.index')
        ->assertSet('permissions.create_pull_requests', true)
        ->assertSet('permissions.create_bugs', true)
        ->assertSet('permissions.merge_pull_requests', false);
});

test('allAgentPermissionKeys returns flat list of all permission keys', function () {
    $keys = Organization::allAgentPermissionKeys();

    expect($keys)->toContain('create_branches')
        ->toContain('push_code')
        ->toContain('merge_pull_requests')
        ->toContain('create_epics')
        ->toContain('trigger_deployments')
        ->toContain('execute_runbooks');

    $totalExpected = 0;
    foreach (Organization::AGENT_PERMISSIONS as $perms) {
        $totalExpected += count($perms);
    }
    expect(count($keys))->toBe($totalExpected);
});

test('agent permissions nav link appears in sidebar', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Agent Permissions');
});
