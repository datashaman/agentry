<?php

namespace App\Http\Controllers;

use App\Services\GitHubAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GitHubAppSetupController extends Controller
{
    public function __invoke(Request $request, GitHubAppService $gitHubAppService): RedirectResponse
    {
        $request->validate([
            'installation_id' => ['required', 'integer'],
        ]);

        $installationId = (int) $request->query('installation_id');

        $installation = $gitHubAppService->getInstallation($installationId);

        if (! $installation) {
            Log::warning('GitHub App setup: could not fetch installation', [
                'installation_id' => $installationId,
            ]);

            return redirect()->route('dashboard')->with('error', 'Could not verify the GitHub App installation.');
        }

        $organization = Auth::user()->currentOrganization();

        if (! $organization) {
            return redirect()->route('dashboard')->with('error', 'No organization selected.');
        }

        $organization->update([
            'github_installation_id' => $installationId,
            'github_account_login' => $installation['account']['login'] ?? null,
            'github_account_type' => $installation['account']['type'] ?? null,
        ]);

        Log::info('GitHub App installed for organization', [
            'organization_id' => $organization->id,
            'installation_id' => $installationId,
            'account_login' => $installation['account']['login'] ?? null,
        ]);

        return redirect()->route('dashboard')->with('status', 'GitHub App installed successfully.');
    }
}
