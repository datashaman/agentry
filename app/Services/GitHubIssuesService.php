<?php

namespace App\Services;

use App\Contracts\WorkItemProvider;
use App\Exceptions\GitHubTokenExpiredException;
use App\Models\Organization;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubIssuesService implements WorkItemProvider
{
    public function __construct(protected GitHubAppService $gitHub) {}

    /**
     * Get a GitHub token: prefer App installation token, fall back to user OAuth token.
     */
    protected function getToken(Organization $org): ?string
    {
        $token = $this->gitHub->getInstallationToken($org);

        if ($token) {
            return $token;
        }

        $user = Auth::user();

        return $user?->github_token;
    }

    /**
     * Check if a response indicates an expired/revoked token and clear it.
     */
    protected function handleUnauthorized(Response $response): void
    {
        if ($response->status() === 401) {
            $user = Auth::user();

            if ($user?->github_token) {
                $user->update(['github_token' => null]);
                Log::warning('GitHub: cleared expired user OAuth token', ['user_id' => $user->id]);
            }

            throw new GitHubTokenExpiredException;
        }
    }

    public function name(): string
    {
        return 'github';
    }

    public function listProjects(Organization $org): array
    {
        $token = $this->getToken($org);

        if (! $token) {
            return [];
        }

        return $org->projects()
            ->with('repos')
            ->get()
            ->flatMap(fn ($project) => $project->repos->map(fn ($repo) => [
                'key' => $repo->gitHubOwnerAndRepo()
                    ? implode('/', $repo->gitHubOwnerAndRepo())
                    : $repo->url,
                'name' => $repo->name,
            ]))
            ->values()
            ->all();
    }

    public function listIssues(Organization $org, string $projectKey, array $filters = []): array
    {
        $token = $this->getToken($org);

        if (! $token) {
            Log::warning('GitHub Issues: no token available (no app installation and no user OAuth token)', [
                'organization_id' => $org->id,
            ]);

            return [];
        }

        $query = [
            'state' => $filters['status'] ?? 'open',
            'per_page' => $filters['maxResults'] ?? 50,
            'sort' => 'updated',
            'direction' => 'desc',
        ];

        if (! empty($filters['type'])) {
            $query['labels'] = $filters['type'];
        }

        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->get("https://api.github.com/repos/{$projectKey}/issues", $query);

        $this->handleUnauthorized($response);

        if (! $response->successful()) {
            Log::warning('GitHub Issues: failed to list issues', [
                'status' => $response->status(),
                'project_key' => $projectKey,
                'body' => $response->json(),
            ]);

            return [];
        }

        return collect($response->json())
            ->filter(fn (array $issue) => ! isset($issue['pull_request']))
            ->map(fn (array $issue) => $this->normalizeIssue($issue))
            ->values()
            ->all();
    }

    public function getIssue(Organization $org, string $issueKey): ?array
    {
        $token = $this->getToken($org);

        if (! $token) {
            return null;
        }

        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->get("https://api.github.com/repos/{$issueKey}");

        $this->handleUnauthorized($response);

        if (! $response->successful()) {
            return null;
        }

        return $this->normalizeIssue($response->json());
    }

    public function listIssueTypes(Organization $org, string $projectKey): array
    {
        $token = $this->getToken($org);

        if (! $token) {
            return [];
        }

        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->get("https://api.github.com/repos/{$projectKey}/labels", ['per_page' => 100]);

        $this->handleUnauthorized($response);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json())
            ->map(fn (array $label) => [
                'id' => (string) $label['id'],
                'name' => $label['name'],
            ])
            ->values()
            ->all();
    }

    public function searchIssues(Organization $org, string $query): array
    {
        $token = $this->getToken($org);

        if (! $token) {
            return [];
        }

        $searchQuery = "{$query} is:issue";

        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->get('https://api.github.com/search/issues', [
                'q' => $searchQuery,
                'per_page' => 25,
            ]);

        $this->handleUnauthorized($response);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('items', []))
            ->map(fn (array $issue) => $this->normalizeIssue($issue))
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, title: string, description: string|null, type: string, status: string, priority: string|null, assignee: string|null, url: string, created_at: string|null, updated_at: string|null}
     */
    protected function normalizeIssue(array $issue): array
    {
        $labels = collect($issue['labels'] ?? [])
            ->pluck('name')
            ->implode(', ');

        return [
            'key' => "#{$issue['number']}",
            'title' => $issue['title'],
            'description' => $issue['body'] ?? null,
            'type' => $labels ?: 'Issue',
            'status' => $issue['state'] ?? 'open',
            'priority' => null,
            'assignee' => $issue['assignee']['login'] ?? null,
            'url' => $issue['html_url'],
            'created_at' => $issue['created_at'] ?? null,
            'updated_at' => $issue['updated_at'] ?? null,
        ];
    }
}
