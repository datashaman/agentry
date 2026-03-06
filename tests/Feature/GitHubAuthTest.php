<?php

use App\Models\User;
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

test('github redirect sends user to github', function () {
    mockSocialiteUser();

    $this->get(route('github.redirect'))
        ->assertRedirect();
});

test('github callback creates new user and logs in', function () {
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

test('github callback logs in existing user by github id', function () {
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
