<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Repo;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubAppService
{
    protected ?string $appId;

    protected ?string $privateKey;

    public function __construct()
    {
        $this->appId = config('services.github.app_id');
        $this->privateKey = config('services.github.app_private_key');
    }

    /**
     * Generate a JWT for authenticating as the GitHub App.
     */
    public function generateJwt(): string
    {
        $now = time();

        return JWT::encode([
            'iat' => $now - 60,
            'exp' => $now + (10 * 60),
            'iss' => $this->appId,
        ], $this->privateKey, 'RS256');
    }

    /**
     * Get an installation access token for an organization.
     * Cached for 55 minutes (tokens expire after 60).
     */
    public function getInstallationToken(Organization $organization): ?string
    {
        if (! $organization->github_installation_id) {
            return null;
        }

        $cacheKey = "github_installation_token:{$organization->github_installation_id}";

        return Cache::remember($cacheKey, 55 * 60, function () use ($organization) {
            $jwt = $this->generateJwt();

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$jwt}",
                'Accept' => 'application/vnd.github+json',
            ])->post("https://api.github.com/app/installations/{$organization->github_installation_id}/access_tokens");

            if (! $response->successful()) {
                return null;
            }

            return $response->json('token');
        });
    }

    /**
     * Fetch installation details from GitHub API.
     *
     * @return array{id: int, account: array{login: string, type: string}}|null
     */
    public function getInstallation(int $installationId): ?array
    {
        $jwt = $this->generateJwt();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$jwt}",
            'Accept' => 'application/vnd.github+json',
        ])->get("https://api.github.com/app/installations/{$installationId}");

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Create a webhook on a GitHub repository.
     *
     * @param  list<string>  $events
     */
    public function createRepoWebhook(Repo $repo, array $events = ['check_suite', 'pull_request']): ?int
    {
        $organization = $repo->project->organization;
        $token = $this->getInstallationToken($organization);

        if (! $token) {
            Log::warning('GitHub: could not get installation token for webhook creation', [
                'repo_id' => $repo->id,
            ]);

            return null;
        }

        $ownerRepo = $repo->gitHubOwnerAndRepo();

        if (! $ownerRepo) {
            Log::warning('GitHub: could not parse owner/repo from URL', [
                'repo_id' => $repo->id,
                'url' => $repo->url,
            ]);

            return null;
        }

        $webhookUrl = route('github.webhook');
        $secret = config('services.github.webhook_secret');

        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->post("https://api.github.com/repos/{$ownerRepo['owner']}/{$ownerRepo['repo']}/hooks", [
                'name' => 'web',
                'active' => true,
                'events' => $events,
                'config' => [
                    'url' => $webhookUrl,
                    'content_type' => 'json',
                    'secret' => $secret,
                    'insecure_ssl' => '0',
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('GitHub: failed to create webhook', [
                'repo_id' => $repo->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        $webhookId = $response->json('id');

        $repo->update(['github_webhook_id' => $webhookId]);

        Log::info('GitHub webhook created', [
            'repo_id' => $repo->id,
            'webhook_id' => $webhookId,
        ]);

        return $webhookId;
    }

    /**
     * Delete a webhook from a GitHub repository.
     */
    public function deleteRepoWebhook(Repo $repo): bool
    {
        if (! $repo->github_webhook_id) {
            return false;
        }

        $organization = $repo->project->organization;
        $token = $this->getInstallationToken($organization);

        if (! $token) {
            return false;
        }

        $ownerRepo = $repo->gitHubOwnerAndRepo();

        if (! $ownerRepo) {
            return false;
        }

        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->delete("https://api.github.com/repos/{$ownerRepo['owner']}/{$ownerRepo['repo']}/hooks/{$repo->github_webhook_id}");

        if ($response->successful() || $response->status() === 404) {
            $repo->update(['github_webhook_id' => null]);

            Log::info('GitHub webhook deleted', [
                'repo_id' => $repo->id,
            ]);

            return true;
        }

        Log::warning('GitHub: failed to delete webhook', [
            'repo_id' => $repo->id,
            'status' => $response->status(),
        ]);

        return false;
    }

    /**
     * Create a check run on a commit.
     *
     * @param  array{title: string, summary: string, text?: string}|null  $output
     * @return int|null The check run ID
     */
    public function createCheckRun(
        Repo $repo,
        string $headSha,
        string $name,
        string $status = 'queued',
        ?array $output = null,
    ): ?int {
        $organization = $repo->project->organization;
        $token = $this->getInstallationToken($organization);

        if (! $token) {
            return null;
        }

        $ownerRepo = $repo->gitHubOwnerAndRepo();

        if (! $ownerRepo) {
            return null;
        }

        $body = [
            'name' => $name,
            'head_sha' => $headSha,
            'status' => $status,
        ];

        if ($output) {
            $body['output'] = $output;
        }

        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->post("https://api.github.com/repos/{$ownerRepo['owner']}/{$ownerRepo['repo']}/check-runs", $body);

        if (! $response->successful()) {
            Log::warning('GitHub: failed to create check run', [
                'repo_id' => $repo->id,
                'head_sha' => $headSha,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        return $response->json('id');
    }

    /**
     * Update an existing check run.
     *
     * @param  array{title: string, summary: string, text?: string}|null  $output
     */
    public function updateCheckRun(
        Repo $repo,
        int $checkRunId,
        string $status = 'in_progress',
        ?string $conclusion = null,
        ?array $output = null,
    ): bool {
        $organization = $repo->project->organization;
        $token = $this->getInstallationToken($organization);

        if (! $token) {
            return false;
        }

        $ownerRepo = $repo->gitHubOwnerAndRepo();

        if (! $ownerRepo) {
            return false;
        }

        $body = ['status' => $status];

        if ($conclusion) {
            $body['conclusion'] = $conclusion;
        }

        if ($output) {
            $body['output'] = $output;
        }

        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->patch("https://api.github.com/repos/{$ownerRepo['owner']}/{$ownerRepo['repo']}/check-runs/{$checkRunId}", $body);

        if (! $response->successful()) {
            Log::warning('GitHub: failed to update check run', [
                'repo_id' => $repo->id,
                'check_run_id' => $checkRunId,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }
}
