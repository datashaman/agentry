<?php

namespace App\Services;

use App\Models\Organization;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
}
