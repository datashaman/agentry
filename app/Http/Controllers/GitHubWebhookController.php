<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GitHubWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('GitHub webhook: invalid signature');

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $event = $request->header('X-GitHub-Event');
        $payload = $request->all();

        Log::info('GitHub webhook received', [
            'event' => $event,
            'action' => $payload['action'] ?? null,
        ]);

        return match ($event) {
            'installation' => $this->handleInstallation($payload),
            'installation_repositories' => $this->handleInstallationRepositories($payload),
            'check_suite' => $this->handleCheckSuite($payload),
            'pull_request' => $this->handlePullRequest($payload),
            default => response()->json(['message' => 'Event ignored']),
        };
    }

    protected function verifySignature(Request $request): bool
    {
        $secret = config('services.github.webhook_secret');

        if (! $secret) {
            return false;
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    protected function handleInstallation(array $payload): JsonResponse
    {
        $action = $payload['action'] ?? null;
        $installationId = $payload['installation']['id'] ?? null;

        if (! $installationId) {
            return response()->json(['error' => 'Missing installation ID'], 422);
        }

        if ($action === 'deleted' || $action === 'suspend') {
            Organization::query()
                ->where('github_installation_id', $installationId)
                ->update([
                    'github_installation_id' => null,
                    'github_account_login' => null,
                    'github_account_type' => null,
                ]);

            Log::info('GitHub App uninstalled/suspended', [
                'installation_id' => $installationId,
                'action' => $action,
            ]);
        }

        if ($action === 'unsuspend') {
            Log::info('GitHub App unsuspended', [
                'installation_id' => $installationId,
            ]);
        }

        return response()->json(['message' => 'Handled']);
    }

    protected function handleInstallationRepositories(array $payload): JsonResponse
    {
        $installationId = $payload['installation']['id'] ?? null;

        Log::info('GitHub App repositories changed', [
            'installation_id' => $installationId,
            'repositories_added' => count($payload['repositories_added'] ?? []),
            'repositories_removed' => count($payload['repositories_removed'] ?? []),
        ]);

        return response()->json(['message' => 'Handled']);
    }

    protected function handleCheckSuite(array $payload): JsonResponse
    {
        $action = $payload['action'] ?? null;

        if ($action !== 'requested' && $action !== 'rerequested') {
            return response()->json(['message' => 'Action ignored']);
        }

        $repoFullName = $payload['repository']['full_name'] ?? null;
        $headSha = $payload['check_suite']['head_sha'] ?? null;

        if (! $repoFullName || ! $headSha) {
            return response()->json(['error' => 'Missing repository or head SHA'], 422);
        }

        $repo = $this->findRepoByFullName($repoFullName);

        if (! $repo) {
            Log::info('GitHub webhook: check_suite for untracked repo', [
                'repo' => $repoFullName,
            ]);

            return response()->json(['message' => 'Repo not tracked']);
        }

        Log::info('GitHub check suite requested', [
            'repo_id' => $repo->id,
            'repo' => $repoFullName,
            'head_sha' => $headSha,
            'action' => $action,
        ]);

        return response()->json(['message' => 'Handled']);
    }

    protected function handlePullRequest(array $payload): JsonResponse
    {
        $action = $payload['action'] ?? null;
        $repoFullName = $payload['repository']['full_name'] ?? null;

        if (! $repoFullName) {
            return response()->json(['error' => 'Missing repository'], 422);
        }

        $repo = $this->findRepoByFullName($repoFullName);

        if (! $repo) {
            return response()->json(['message' => 'Repo not tracked']);
        }

        Log::info('GitHub pull request event', [
            'repo_id' => $repo->id,
            'repo' => $repoFullName,
            'action' => $action,
            'pr_number' => $payload['pull_request']['number'] ?? null,
            'head_sha' => $payload['pull_request']['head']['sha'] ?? null,
        ]);

        return response()->json(['message' => 'Handled']);
    }

    protected function findRepoByFullName(string $fullName): ?Repo
    {
        return Repo::query()
            ->where('url', 'like', "%github.com/{$fullName}%")
            ->orWhere('url', 'like', "%github.com:{$fullName}%")
            ->first();
    }
}
