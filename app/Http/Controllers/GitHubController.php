<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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

        Auth::user()->update([
            'github_id' => $githubUser->getId(),
            'github_token' => $githubUser->token,
            'github_nickname' => $githubUser->getNickname(),
        ]);

        session()->flash('status', 'github-connected');

        return redirect()->route('github.edit');
    }
}
