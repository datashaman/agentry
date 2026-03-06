<?php

use App\Models\Organization;
use App\Services\GitHubAppService;
use App\Services\GitHubIssuesService;
use Illuminate\Support\Facades\Http;

test('list issues returns normalized github issues', function () {
    $org = Organization::factory()->create(['github_installation_id' => 123]);

    $gitHub = Mockery::mock(GitHubAppService::class);
    $gitHub->shouldReceive('getInstallationToken')->andReturn('test-token');

    Http::fake([
        'api.github.com/repos/owner/repo/issues*' => Http::response([
            [
                'number' => 42,
                'title' => 'Fix button alignment',
                'state' => 'open',
                'labels' => [['name' => 'bug']],
                'assignee' => ['login' => 'octocat'],
                'html_url' => 'https://github.com/owner/repo/issues/42',
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-03-01T00:00:00Z',
            ],
        ]),
    ]);

    $service = new GitHubIssuesService($gitHub);
    $issues = $service->listIssues($org, 'owner/repo');

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['key'])->toBe('#42')
        ->and($issues[0]['title'])->toBe('Fix button alignment')
        ->and($issues[0]['type'])->toBe('bug')
        ->and($issues[0]['status'])->toBe('open')
        ->and($issues[0]['assignee'])->toBe('octocat');
});

test('list issues filters out pull requests', function () {
    $org = Organization::factory()->create(['github_installation_id' => 123]);

    $gitHub = Mockery::mock(GitHubAppService::class);
    $gitHub->shouldReceive('getInstallationToken')->andReturn('test-token');

    Http::fake([
        'api.github.com/repos/owner/repo/issues*' => Http::response([
            [
                'number' => 1,
                'title' => 'Real issue',
                'state' => 'open',
                'labels' => [],
                'assignee' => null,
                'html_url' => 'https://github.com/owner/repo/issues/1',
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
            ],
            [
                'number' => 2,
                'title' => 'A pull request',
                'state' => 'open',
                'labels' => [],
                'pull_request' => ['url' => 'https://api.github.com/repos/owner/repo/pulls/2'],
                'assignee' => null,
                'html_url' => 'https://github.com/owner/repo/pulls/2',
                'created_at' => '2026-01-01T00:00:00Z',
                'updated_at' => '2026-01-01T00:00:00Z',
            ],
        ]),
    ]);

    $service = new GitHubIssuesService($gitHub);
    $issues = $service->listIssues($org, 'owner/repo');

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['title'])->toBe('Real issue');
});

test('list issues returns empty when no token', function () {
    $org = Organization::factory()->create();

    $gitHub = Mockery::mock(GitHubAppService::class);
    $gitHub->shouldReceive('getInstallationToken')->andReturn(null);

    $service = new GitHubIssuesService($gitHub);
    $issues = $service->listIssues($org, 'owner/repo');

    expect($issues)->toBeEmpty();
});

test('get issue returns normalized issue', function () {
    $org = Organization::factory()->create(['github_installation_id' => 123]);

    $gitHub = Mockery::mock(GitHubAppService::class);
    $gitHub->shouldReceive('getInstallationToken')->andReturn('test-token');

    Http::fake([
        'api.github.com/repos/owner/repo/issues/5' => Http::response([
            'number' => 5,
            'title' => 'Single issue',
            'state' => 'closed',
            'labels' => [['name' => 'enhancement']],
            'assignee' => null,
            'html_url' => 'https://github.com/owner/repo/issues/5',
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-02-01T00:00:00Z',
        ]),
    ]);

    $service = new GitHubIssuesService($gitHub);
    $issue = $service->getIssue($org, 'owner/repo/issues/5');

    expect($issue)->not->toBeNull()
        ->and($issue['key'])->toBe('#5')
        ->and($issue['status'])->toBe('closed');
});

test('search issues returns results', function () {
    $org = Organization::factory()->create(['github_installation_id' => 123]);

    $gitHub = Mockery::mock(GitHubAppService::class);
    $gitHub->shouldReceive('getInstallationToken')->andReturn('test-token');

    Http::fake([
        'api.github.com/search/issues*' => Http::response([
            'items' => [
                [
                    'number' => 10,
                    'title' => 'Found issue',
                    'state' => 'open',
                    'labels' => [],
                    'assignee' => null,
                    'html_url' => 'https://github.com/owner/repo/issues/10',
                    'created_at' => '2026-01-01T00:00:00Z',
                    'updated_at' => '2026-01-01T00:00:00Z',
                ],
            ],
        ]),
    ]);

    $service = new GitHubIssuesService($gitHub);
    $results = $service->searchIssues($org, 'found');

    expect($results)->toHaveCount(1)
        ->and($results[0]['title'])->toBe('Found issue');
});

test('name returns github', function () {
    $gitHub = Mockery::mock(GitHubAppService::class);
    $service = new GitHubIssuesService($gitHub);

    expect($service->name())->toBe('github');
});
