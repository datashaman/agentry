<?php

namespace App\Services;

use App\Contracts\WorkItemProvider;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JiraService implements WorkItemProvider
{
    public function name(): string
    {
        return 'jira';
    }

    public function listProjects(Organization $org): array
    {
        $user = $this->resolveUser($org);

        if (! $user) {
            return [];
        }

        $response = $this->request($user)
            ->get($this->baseUrl($user).'/project/search', ['maxResults' => 100]);

        if (! $response->successful()) {
            Log::warning('Jira: failed to list projects', ['status' => $response->status()]);

            return [];
        }

        return collect($response->json('values', []))
            ->map(fn (array $project) => [
                'key' => $project['key'],
                'name' => $project['name'],
            ])
            ->values()
            ->all();
    }

    public function listIssues(Organization $org, string $projectKey, array $filters = []): array
    {
        $user = $this->resolveUser($org);

        if (! $user) {
            return [];
        }

        $jql = "project = \"{$projectKey}\"";

        if (! empty($filters['status'])) {
            $jql .= " AND status = \"{$filters['status']}\"";
        }

        if (! empty($filters['type'])) {
            $jql .= " AND issuetype = \"{$filters['type']}\"";
        }

        $jql .= ' ORDER BY updated DESC';

        $response = $this->request($user)
            ->get($this->baseUrl($user).'/search', [
                'jql' => $jql,
                'maxResults' => $filters['maxResults'] ?? 50,
                'fields' => 'summary,issuetype,status,priority,assignee,created,updated',
            ]);

        if (! $response->successful()) {
            Log::warning('Jira: failed to list issues', ['status' => $response->status()]);

            return [];
        }

        return collect($response->json('issues', []))
            ->map(fn (array $issue) => $this->normalizeIssue($issue, $user))
            ->values()
            ->all();
    }

    public function getIssue(Organization $org, string $issueKey): ?array
    {
        $user = $this->resolveUser($org);

        if (! $user) {
            return null;
        }

        $response = $this->request($user)
            ->get($this->baseUrl($user)."/issue/{$issueKey}", [
                'fields' => 'summary,issuetype,status,priority,assignee,created,updated',
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $this->normalizeIssue($response->json(), $user);
    }

    public function listIssueTypes(Organization $org, string $projectKey): array
    {
        $user = $this->resolveUser($org);

        if (! $user) {
            return [];
        }

        $response = $this->request($user)
            ->get($this->baseUrl($user)."/project/{$projectKey}");

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('issueTypes', []))
            ->map(fn (array $type) => [
                'id' => (string) $type['id'],
                'name' => $type['name'],
            ])
            ->values()
            ->all();
    }

    public function searchIssues(Organization $org, string $query): array
    {
        $user = $this->resolveUser($org);

        if (! $user) {
            return [];
        }

        $jql = "text ~ \"{$query}\" ORDER BY updated DESC";

        $response = $this->request($user)
            ->get($this->baseUrl($user).'/search', [
                'jql' => $jql,
                'maxResults' => 25,
                'fields' => 'summary,issuetype,status,priority,assignee,created,updated',
            ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('issues', []))
            ->map(fn (array $issue) => $this->normalizeIssue($issue, $user))
            ->values()
            ->all();
    }

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshToken(User $user): bool
    {
        if (! $user->jira_refresh_token) {
            return false;
        }

        $response = Http::asForm()->post('https://auth.atlassian.com/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.jira.client_id'),
            'client_secret' => config('services.jira.client_secret'),
            'refresh_token' => $user->jira_refresh_token,
        ]);

        if (! $response->successful()) {
            Log::warning('Jira: failed to refresh token', ['status' => $response->status()]);

            return false;
        }

        $user->update([
            'jira_token' => $response->json('access_token'),
            'jira_refresh_token' => $response->json('refresh_token'),
        ]);

        return true;
    }

    protected function resolveUser(Organization $org): ?User
    {
        return $org->users()->whereNotNull('jira_token')->first();
    }

    protected function request(User $user): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($user->jira_token)
            ->accept('application/json');
    }

    protected function baseUrl(User $user): string
    {
        return "https://api.atlassian.com/ex/jira/{$user->jira_cloud_id}/rest/api/3";
    }

    /**
     * @return array{key: string, title: string, type: string, status: string, priority: string|null, assignee: string|null, url: string, created_at: string|null, updated_at: string|null}
     */
    protected function normalizeIssue(array $issue, User $user): array
    {
        $fields = $issue['fields'] ?? [];

        return [
            'key' => $issue['key'],
            'title' => $fields['summary'] ?? '',
            'type' => $fields['issuetype']['name'] ?? 'Unknown',
            'status' => $fields['status']['name'] ?? 'Unknown',
            'priority' => $fields['priority']['name'] ?? null,
            'assignee' => $fields['assignee']['displayName'] ?? null,
            'url' => "https://{$user->jira_cloud_id}.atlassian.net/browse/{$issue['key']}",
            'created_at' => $fields['created'] ?? null,
            'updated_at' => $fields['updated'] ?? null,
        ];
    }
}
