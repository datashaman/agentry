<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\GitHubAppService;

test('setup stores github installation on current organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $user->organizations()->attach($organization);

    $this->mock(GitHubAppService::class, function ($mock) {
        $mock->shouldReceive('getInstallation')
            ->with(12345)
            ->andReturn([
                'id' => 12345,
                'account' => [
                    'login' => 'acme-org',
                    'type' => 'Organization',
                ],
            ]);
    });

    $this->actingAs($user)
        ->get(route('github.app.setup', ['installation_id' => 12345]))
        ->assertRedirect(route('dashboard'));

    $organization->refresh();
    expect($organization->github_installation_id)->toBe(12345);
    expect($organization->github_account_login)->toBe('acme-org');
    expect($organization->github_account_type)->toBe('Organization');
});

test('setup redirects with error when installation not found on github', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $user->organizations()->attach($organization);

    $this->mock(GitHubAppService::class, function ($mock) {
        $mock->shouldReceive('getInstallation')
            ->with(99999)
            ->andReturn(null);
    });

    $this->actingAs($user)
        ->get(route('github.app.setup', ['installation_id' => 99999]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error');

    $organization->refresh();
    expect($organization->github_installation_id)->toBeNull();
});

test('setup redirects with error when no current organization', function () {
    $user = User::factory()->create(['current_organization_id' => null]);

    $this->mock(GitHubAppService::class, function ($mock) {
        $mock->shouldReceive('getInstallation')
            ->with(12345)
            ->andReturn([
                'id' => 12345,
                'account' => [
                    'login' => 'acme-org',
                    'type' => 'Organization',
                ],
            ]);
    });

    $this->actingAs($user)
        ->get(route('github.app.setup', ['installation_id' => 12345]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error');
});

test('setup requires authentication', function () {
    $this->get(route('github.app.setup', ['installation_id' => 12345]))
        ->assertRedirect(route('login'));
});

test('setup requires installation_id parameter', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->get(route('github.app.setup'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('installation_id');
});

test('hasGitHubApp returns true when installation id is set', function () {
    $organization = Organization::factory()->create([
        'github_installation_id' => 12345,
    ]);

    expect($organization->hasGitHubApp())->toBeTrue();
});

test('hasGitHubApp returns false when installation id is null', function () {
    $organization = Organization::factory()->create();

    expect($organization->hasGitHubApp())->toBeFalse();
});
