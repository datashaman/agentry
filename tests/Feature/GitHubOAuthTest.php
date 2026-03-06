<?php

use App\Models\Organization;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('github redirect sends user to github', function () {
    $user = User::factory()->withOrganization(Organization::factory()->create())->create();

    $response = $this->actingAs($user)->get(route('github.redirect'));

    $response->assertRedirectContains('github.com');
});

test('github callback stores token and redirects', function () {
    $user = User::factory()->withOrganization(Organization::factory()->create())->create();

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 12345;
    $socialiteUser->token = 'gho_test_token_123';
    $socialiteUser->nickname = 'octocat';

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

    $response = $this->actingAs($user)->get(route('github.callback'));

    $response->assertRedirect(route('github.edit'));

    $user->refresh();
    expect($user->github_id)->toBe(12345);
    expect($user->github_nickname)->toBe('octocat');
    expect($user->hasGitHub())->toBeTrue();
});

test('disconnect clears github fields', function () {
    $user = User::factory()->withOrganization(Organization::factory()->create())->create([
        'github_id' => 12345,
        'github_token' => 'gho_test_token_123',
        'github_nickname' => 'octocat',
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::settings.github')
        ->call('disconnect');

    $user->refresh();
    expect($user->github_id)->toBeNull();
    expect($user->github_token)->toBeNull();
    expect($user->github_nickname)->toBeNull();
    expect($user->hasGitHub())->toBeFalse();
});

test('settings page shows connect button when not connected', function () {
    $user = User::factory()->withOrganization(Organization::factory()->create())->create();

    $response = $this->actingAs($user)->get(route('github.edit'));

    $response->assertOk();
    $response->assertSee('Connect GitHub');
});

test('settings page shows disconnect button when connected', function () {
    $user = User::factory()->withOrganization(Organization::factory()->create())->create([
        'github_id' => 12345,
        'github_token' => 'gho_test_token_123',
        'github_nickname' => 'octocat',
    ]);

    $response = $this->actingAs($user)->get(route('github.edit'));

    $response->assertOk();
    $response->assertSee('Disconnect GitHub');
    $response->assertSee('octocat');
});
