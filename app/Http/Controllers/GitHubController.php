<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')->scopes(['repo', 'read:user'])->redirect();
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

        Auth::login($user, remember: true);

        return redirect()->route('dashboard');
    }
}
