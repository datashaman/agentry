<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')->scopes(['repo', 'read:org'])->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        if (Auth::check()) {
            return $this->connectToExistingUser($githubUser);
        }

        return $this->loginOrRegister($githubUser);
    }

    protected function connectToExistingUser($githubUser): RedirectResponse
    {
        Auth::user()->update([
            'github_id' => $githubUser->getId(),
            'github_token' => $githubUser->token,
            'github_nickname' => $githubUser->getNickname(),
        ]);

        $this->syncGitHubOrganizations(Auth::user(), $githubUser->token);

        session()->flash('status', 'github-connected');

        return redirect()->route('github.edit');
    }

    protected function loginOrRegister($githubUser): RedirectResponse
    {
        $user = User::query()->where('github_id', $githubUser->getId())->first();

        if ($user) {
            $user->update([
                'github_token' => $githubUser->token,
                'github_nickname' => $githubUser->getNickname(),
            ]);

            $this->syncGitHubOrganizations($user, $githubUser->token);

            Auth::login($user, remember: true);

            return redirect()->route('dashboard');
        }

        $user = User::query()->where('email', $githubUser->getEmail())->first();

        if ($user) {
            $user->update([
                'github_id' => $githubUser->getId(),
                'github_token' => $githubUser->token,
                'github_nickname' => $githubUser->getNickname(),
            ]);

            $this->syncGitHubOrganizations($user, $githubUser->token);

            Auth::login($user, remember: true);

            return redirect()->route('dashboard');
        }

        $user = User::create([
            'name' => $githubUser->getName() ?? $githubUser->getNickname(),
            'email' => $githubUser->getEmail(),
            'password' => Str::random(32),
            'github_id' => $githubUser->getId(),
            'github_token' => $githubUser->token,
            'github_nickname' => $githubUser->getNickname(),
        ]);

        $user->createPersonalOrganization();
        $this->syncGitHubOrganizations($user, $githubUser->token);

        Auth::login($user, remember: true);

        return redirect()->route('dashboard');
    }

    protected function syncGitHubOrganizations(User $user, string $token): void
    {
        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->get('https://api.github.com/user/orgs');

        if (! $response->successful()) {
            Log::warning('GitHub: failed to fetch user organizations', [
                'user_id' => $user->id,
                'status' => $response->status(),
            ]);

            return;
        }

        foreach ($response->json() as $githubOrg) {
            $organization = Organization::query()
                ->where('provider', 'github')
                ->where('provider_id', (string) $githubOrg['id'])
                ->first();

            if (! $organization) {
                $slug = Str::slug($githubOrg['login']);
                $baseSlug = $slug;
                $counter = 1;
                while (Organization::query()->where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$counter;
                    $counter++;
                }

                $organization = Organization::create([
                    'name' => $githubOrg['login'],
                    'slug' => $slug,
                    'provider' => 'github',
                    'provider_id' => (string) $githubOrg['id'],
                ]);
            }

            if (! $user->organizations()->where('organizations.id', $organization->id)->exists()) {
                $user->organizations()->attach($organization, ['role' => 'member']);
            }
        }
    }
}
