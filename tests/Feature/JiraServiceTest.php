<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\JiraService;
use Illuminate\Support\Facades\Http;

test('list projects returns normalized project list', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->withOrganization($org)->create([
        'jira_token' => 'test-token',
        'jira_cloud_id' => 'cloud-123',
    ]);

    Http::fake([
        'api.atlassian.com/ex/jira/cloud-123/rest/api/3/project/search*' => Http::response([
            'values' => [
                ['key' => 'PROJ', 'name' => 'My Project'],
                ['key' => 'TEAM', 'name' => 'Team Project'],
            ],
        ]),
    ]);

    $service = new JiraService;
    $projects = $service->listProjects($org);

    expect($projects)->toHaveCount(2)
        ->and($projects[0])->toBe(['key' => 'PROJ', 'name' => 'My Project'])
        ->and($projects[1])->toBe(['key' => 'TEAM', 'name' => 'Team Project']);
});

test('list projects returns empty when no jira user', function () {
    $org = Organization::factory()->create();

    $service = new JiraService;
    $projects = $service->listProjects($org);

    expect($projects)->toBeEmpty();
});

test('list issues returns normalized issues', function () {
    $org = Organization::factory()->create();
    User::factory()->withOrganization($org)->create([
        'jira_token' => 'test-token',
        'jira_cloud_id' => 'cloud-123',
    ]);

    Http::fake([
        'api.atlassian.com/ex/jira/cloud-123/rest/api/3/search*' => Http::response([
            'issues' => [
                [
                    'key' => 'PROJ-1',
                    'fields' => [
                        'summary' => 'Fix the login bug',
                        'issuetype' => ['name' => 'Bug'],
                        'status' => ['name' => 'In Progress'],
                        'priority' => ['name' => 'High'],
                        'assignee' => ['displayName' => 'Jane Doe'],
                        'created' => '2026-01-01T00:00:00.000+0000',
                        'updated' => '2026-03-01T00:00:00.000+0000',
                    ],
                ],
            ],
        ]),
    ]);

    $service = new JiraService;
    $issues = $service->listIssues($org, 'PROJ');

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['key'])->toBe('PROJ-1')
        ->and($issues[0]['title'])->toBe('Fix the login bug')
        ->and($issues[0]['type'])->toBe('Bug')
        ->and($issues[0]['status'])->toBe('In Progress')
        ->and($issues[0]['priority'])->toBe('High')
        ->and($issues[0]['assignee'])->toBe('Jane Doe');
});

test('get issue returns single normalized issue', function () {
    $org = Organization::factory()->create();
    User::factory()->withOrganization($org)->create([
        'jira_token' => 'test-token',
        'jira_cloud_id' => 'cloud-123',
    ]);

    Http::fake([
        'api.atlassian.com/ex/jira/cloud-123/rest/api/3/issue/PROJ-1*' => Http::response([
            'key' => 'PROJ-1',
            'fields' => [
                'summary' => 'A single issue',
                'issuetype' => ['name' => 'Task'],
                'status' => ['name' => 'To Do'],
                'priority' => null,
                'assignee' => null,
                'created' => '2026-01-01T00:00:00.000+0000',
                'updated' => '2026-01-02T00:00:00.000+0000',
            ],
        ]),
    ]);

    $service = new JiraService;
    $issue = $service->getIssue($org, 'PROJ-1');

    expect($issue)->not->toBeNull()
        ->and($issue['key'])->toBe('PROJ-1')
        ->and($issue['title'])->toBe('A single issue')
        ->and($issue['assignee'])->toBeNull();
});

test('get issue returns null on failure', function () {
    $org = Organization::factory()->create();
    User::factory()->withOrganization($org)->create([
        'jira_token' => 'test-token',
        'jira_cloud_id' => 'cloud-123',
    ]);

    Http::fake([
        'api.atlassian.com/*' => Http::response([], 404),
    ]);

    $service = new JiraService;
    $issue = $service->getIssue($org, 'PROJ-999');

    expect($issue)->toBeNull();
});

test('search issues performs text search', function () {
    $org = Organization::factory()->create();
    User::factory()->withOrganization($org)->create([
        'jira_token' => 'test-token',
        'jira_cloud_id' => 'cloud-123',
    ]);

    Http::fake([
        'api.atlassian.com/ex/jira/cloud-123/rest/api/3/search*' => Http::response([
            'issues' => [
                [
                    'key' => 'PROJ-5',
                    'fields' => [
                        'summary' => 'Search result',
                        'issuetype' => ['name' => 'Story'],
                        'status' => ['name' => 'Open'],
                        'priority' => null,
                        'assignee' => null,
                        'created' => null,
                        'updated' => null,
                    ],
                ],
            ],
        ]),
    ]);

    $service = new JiraService;
    $results = $service->searchIssues($org, 'search query');

    expect($results)->toHaveCount(1)
        ->and($results[0]['key'])->toBe('PROJ-5');
});

test('refresh token updates user tokens', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->withOrganization($org)->create([
        'jira_token' => 'old-token',
        'jira_refresh_token' => 'old-refresh-token',
        'jira_cloud_id' => 'cloud-123',
    ]);

    Http::fake([
        'auth.atlassian.com/oauth/token' => Http::response([
            'access_token' => 'new-token',
            'refresh_token' => 'new-refresh-token',
        ]),
    ]);

    $service = new JiraService;
    $result = $service->refreshToken($user);

    expect($result)->toBeTrue();

    $user->refresh();
    expect($user->jira_token)->toBe('new-token')
        ->and($user->jira_refresh_token)->toBe('new-refresh-token');
});
