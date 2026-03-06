<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('jira redirect requires authentication', function () {
    $response = $this->get(route('jira.redirect'));

    $response->assertRedirect(route('login'));
});

test('jira callback stores token and cloud id', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    Http::fake([
        'api.atlassian.com/oauth/token/accessible-resources' => Http::response([
            ['id' => 'cloud-abc-123', 'name' => 'My Jira Site'],
        ]),
    ]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'atlassian-user-123';
    $socialiteUser->token = 'jira-access-token';
    $socialiteUser->refreshToken = 'jira-refresh-token';

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('atlassian')->andReturn($provider);

    $response = $this->actingAs($user)->get(route('jira.callback'));

    $response->assertRedirect(route('jira.edit'));

    $user->refresh();
    expect($user->jira_account_id)->toBe('atlassian-user-123')
        ->and($user->jira_token)->toBe('jira-access-token')
        ->and($user->jira_refresh_token)->toBe('jira-refresh-token')
        ->and($user->jira_cloud_id)->toBe('cloud-abc-123')
        ->and($user->hasJira())->toBeTrue();
});

test('jira callback handles failed cloud id fetch', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    Http::fake([
        'api.atlassian.com/oauth/token/accessible-resources' => Http::response([], 401),
    ]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 'atlassian-user-123';
    $socialiteUser->token = 'jira-access-token';
    $socialiteUser->refreshToken = 'jira-refresh-token';

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('atlassian')->andReturn($provider);

    $response = $this->actingAs($user)->get(route('jira.callback'));

    $response->assertRedirect(route('jira.edit'));

    $user->refresh();
    expect($user->jira_token)->toBeNull();
});

test('jira settings page loads for authenticated user', function () {
    $user = User::factory()->withOrganization(Organization::factory()->create())->create();

    $response = $this->actingAs($user)->get(route('jira.edit'));

    $response->assertOk();
    $response->assertSee('Connect Jira');
});

test('jira settings page shows connected state', function () {
    $user = User::factory()->withOrganization(Organization::factory()->create())->create([
        'jira_account_id' => 'atlassian-123',
        'jira_token' => 'test-token',
        'jira_cloud_id' => 'cloud-123',
    ]);

    $response = $this->actingAs($user)->get(route('jira.edit'));

    $response->assertOk();
    $response->assertSee('Disconnect Jira');
    $response->assertSee('cloud-123');
});
