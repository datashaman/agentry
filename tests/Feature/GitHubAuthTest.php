<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

function mockSocialiteUser(
    int $id = 12345,
    string $name = 'Test User',
    string $email = 'test@example.com',
    string $nickname = 'testuser',
    string $token = 'github-token',
): void {
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getName')->andReturn($name);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getNickname')->andReturn($nickname);
    $socialiteUser->token = $token;

    $driver = Mockery::mock();
    $driver->shouldReceive('user')->andReturn($socialiteUser);
    $driver->shouldReceive('scopes')->andReturnSelf();
    $driver->shouldReceive('redirect')->andReturn(redirect('https://github.com/login/oauth'));

    Socialite::shouldReceive('driver')->with('github')->andReturn($driver);
}

function fakeEmptyOrgs(): void
{
    Http::fake(['api.github.com/user/orgs' => Http::response([])]);
}

test('github redirect sends user to github', function () {
    mockSocialiteUser();

    $this->get(route('github.redirect'))
        ->assertRedirect();
});

test('github callback creates new user and logs in', function () {
    fakeEmptyOrgs();
    mockSocialiteUser();

    $this->get(route('github.callback'))
        ->assertRedirect(route('dashboard'));

    $user = User::query()->where('github_id', 12345)->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->github_nickname)->toBe('testuser');
    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->id);
});

test('github callback creates personal organization for new user', function () {
    fakeEmptyOrgs();
    mockSocialiteUser();

    $this->get(route('github.callback'));

    $user = User::query()->where('github_id', 12345)->first();

    expect($user->organizations)->toHaveCount(1);
    expect($user->currentOrganization())->not->toBeNull();
    expect($user->currentOrganization()->provider)->toBe('agentry');
});

test('github callback syncs github organizations', function () {
    Http::fake([
        'api.github.com/user/orgs' => Http::response([
            ['id' => 111, 'login' => 'acme-corp'],
            ['id' => 222, 'login' => 'widgets-inc'],
        ]),
    ]);

    mockSocialiteUser();

    $this->get(route('github.callback'));

    $user = User::query()->where('github_id', 12345)->first();

    // Personal org + 2 GitHub orgs
    expect($user->organizations)->toHaveCount(3);

    $githubOrgs = $user->organizations->where('provider', 'github');
    expect($githubOrgs)->toHaveCount(2);
    expect($githubOrgs->pluck('name')->sort()->values()->all())->toBe(['acme-corp', 'widgets-inc']);
});

test('github callback does not duplicate existing github organizations', function () {
    Http::fake([
        'api.github.com/user/orgs' => Http::response([
            ['id' => 111, 'login' => 'acme-corp'],
        ]),
    ]);

    Organization::factory()->create([
        'name' => 'acme-corp',
        'slug' => 'acme-corp',
        'provider' => 'github',
        'provider_id' => '111',
    ]);

    mockSocialiteUser();

    $this->get(route('github.callback'));

    expect(Organization::query()->where('provider', 'github')->where('provider_id', '111')->count())->toBe(1);
});

test('github callback attaches existing user to existing github org', function () {
    Http::fake([
        'api.github.com/user/orgs' => Http::response([
            ['id' => 111, 'login' => 'acme-corp'],
        ]),
    ]);

    $existingOrg = Organization::factory()->create([
        'provider' => 'github',
        'provider_id' => '111',
    ]);

    $user = User::factory()->create([
        'github_id' => 12345,
        'github_nickname' => 'testuser',
    ]);

    mockSocialiteUser();

    $this->get(route('github.callback'));

    expect($user->fresh()->organizations->pluck('id'))->toContain($existingOrg->id);
});

test('github callback logs in existing user by github id', function () {
    fakeEmptyOrgs();

    $user = User::factory()->create([
        'github_id' => 12345,
        'github_nickname' => 'oldnickname',
    ]);

    mockSocialiteUser();

    $this->get(route('github.callback'))
        ->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($user->id);
    expect($user->fresh()->github_nickname)->toBe('testuser');
});

test('github callback links existing user by email', function () {
    fakeEmptyOrgs();

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'github_id' => null,
    ]);

    mockSocialiteUser();

    $this->get(route('github.callback'))
        ->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($user->id);
    expect($user->fresh()->github_id)->toBe(12345);
});

test('github callback connects account when already authenticated', function () {
    fakeEmptyOrgs();

    $user = User::factory()->create(['github_id' => null]);

    mockSocialiteUser();

    $this->actingAs($user)
        ->get(route('github.callback'))
        ->assertRedirect(route('github.edit'));

    expect($user->fresh()->github_id)->toBe(12345);
});

test('login page shows github button', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Continue with GitHub');
});

test('register page shows github button', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Continue with GitHub');
});

test('github redirect is accessible without authentication', function () {
    mockSocialiteUser();

    $this->get(route('github.redirect'))
        ->assertRedirect();
});
