<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Services\GitHubAppService;
use Illuminate\Support\Facades\Http;

function mockCheckRunService(): GitHubAppService
{
    $service = Mockery::mock(GitHubAppService::class)->makePartial();
    $service->shouldReceive('getInstallationToken')->andReturn('test-token');
    app()->instance(GitHubAppService::class, $service);

    return $service;
}

test('createCheckRun creates a check run and returns id', function () {
    Http::fake([
        'api.github.com/repos/acme/my-repo/check-runs' => Http::response(['id' => 42], 201),
    ]);

    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
    ]);

    $service = mockCheckRunService();
    $checkRunId = $service->createCheckRun($repo, 'abc123', 'Agentry Review');

    expect($checkRunId)->toBe(42);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/check-runs')
            && $request['name'] === 'Agentry Review'
            && $request['head_sha'] === 'abc123'
            && $request['status'] === 'queued';
    });
});

test('createCheckRun includes output when provided', function () {
    Http::fake([
        'api.github.com/repos/acme/my-repo/check-runs' => Http::response(['id' => 42], 201),
    ]);

    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
    ]);

    $service = mockCheckRunService();
    $service->createCheckRun($repo, 'abc123', 'Agentry Review', 'in_progress', [
        'title' => 'Reviewing',
        'summary' => 'Agent is reviewing the code',
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/check-runs')
            && $request['output']['title'] === 'Reviewing';
    });
});

test('createCheckRun returns null when token unavailable', function () {
    $service = Mockery::mock(GitHubAppService::class)->makePartial();
    $service->shouldReceive('getInstallationToken')->andReturn(null);

    $organization = Organization::factory()->create(['github_installation_id' => null]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
    ]);

    expect($service->createCheckRun($repo, 'abc123', 'Agentry Review'))->toBeNull();
});

test('updateCheckRun updates status and conclusion', function () {
    Http::fake([
        'api.github.com/repos/acme/my-repo/check-runs/42' => Http::response(['id' => 42]),
    ]);

    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
    ]);

    $service = mockCheckRunService();
    $result = $service->updateCheckRun($repo, 42, 'completed', 'success', [
        'title' => 'Review Complete',
        'summary' => 'All checks passed',
    ]);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/check-runs/42')
            && $request->method() === 'PATCH'
            && $request['status'] === 'completed'
            && $request['conclusion'] === 'success'
            && $request['output']['title'] === 'Review Complete';
    });
});

test('updateCheckRun returns false when token unavailable', function () {
    $service = Mockery::mock(GitHubAppService::class)->makePartial();
    $service->shouldReceive('getInstallationToken')->andReturn(null);

    $organization = Organization::factory()->create(['github_installation_id' => null]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
    ]);

    expect($service->updateCheckRun($repo, 42, 'completed', 'success'))->toBeFalse();
});

test('webhook handles check_suite requested event', function () {
    config(['services.github.webhook_secret' => 'test-secret']);

    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
    ]);

    $payload = [
        'action' => 'requested',
        'check_suite' => ['head_sha' => 'abc123'],
        'repository' => ['full_name' => 'acme/my-repo'],
    ];

    $json = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $json, 'test-secret');

    $this->postJson(route('github.webhook'), $payload, [
        'X-GitHub-Event' => 'check_suite',
        'X-Hub-Signature-256' => $signature,
    ])->assertOk();
});

test('webhook handles pull_request event', function () {
    config(['services.github.webhook_secret' => 'test-secret']);

    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
    ]);

    $payload = [
        'action' => 'opened',
        'pull_request' => [
            'number' => 5,
            'head' => ['sha' => 'def456'],
        ],
        'repository' => ['full_name' => 'acme/my-repo'],
    ];

    $json = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $json, 'test-secret');

    $this->postJson(route('github.webhook'), $payload, [
        'X-GitHub-Event' => 'pull_request',
        'X-Hub-Signature-256' => $signature,
    ])->assertOk();
});

test('webhook returns repo not tracked for unknown repo', function () {
    config(['services.github.webhook_secret' => 'test-secret']);

    $payload = [
        'action' => 'requested',
        'check_suite' => ['head_sha' => 'abc123'],
        'repository' => ['full_name' => 'unknown/repo'],
    ];

    $json = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $json, 'test-secret');

    $this->postJson(route('github.webhook'), $payload, [
        'X-GitHub-Event' => 'check_suite',
        'X-Hub-Signature-256' => $signature,
    ])->assertOk()
        ->assertJson(['message' => 'Repo not tracked']);
});
