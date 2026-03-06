<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class JiraController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('atlassian')
            ->scopes(['read:jira-work', 'read:jira-user', 'write:jira-work', 'offline_access'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $atlassianUser = Socialite::driver('atlassian')->user();

        $cloudId = $this->fetchCloudId($atlassianUser->token);

        if (! $cloudId) {
            session()->flash('status', 'jira-error');

            return redirect()->route('jira.edit');
        }

        Auth::user()->update([
            'jira_account_id' => $atlassianUser->getId(),
            'jira_token' => $atlassianUser->token,
            'jira_refresh_token' => $atlassianUser->refreshToken,
            'jira_cloud_id' => $cloudId,
        ]);

        session()->flash('status', 'jira-connected');

        return redirect()->route('jira.edit');
    }

    protected function fetchCloudId(string $token): ?string
    {
        $response = Http::withToken($token)
            ->get('https://api.atlassian.com/oauth/token/accessible-resources');

        if (! $response->successful() || empty($response->json())) {
            Log::warning('Jira: failed to fetch accessible resources', [
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json('0.id');
    }
}
