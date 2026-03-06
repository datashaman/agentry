<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Repo;
use App\Services\GitHubAppService;
use Illuminate\Support\Facades\Http;

function mockGitHubAppService(): GitHubAppService
{
    $service = Mockery::mock(GitHubAppService::class)->makePartial();
    $service->shouldReceive('getInstallationToken')->andReturn('test-token');
    app()->instance(GitHubAppService::class, $service);

    return $service;
}

test('createRepoWebhook creates webhook and stores id on repo', function () {
    Http::fake([
        'api.github.com/repos/acme/my-repo/hooks' => Http::response(['id' => 777], 201),
    ]);

    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
    ]);

    $service = mockGitHubAppService();
    $webhookId = $service->createRepoWebhook($repo);

    expect($webhookId)->toBe(777);
    expect($repo->fresh()->github_webhook_id)->toBe(777);
});

test('createRepoWebhook returns null when installation token unavailable', function () {
    $service = Mockery::mock(GitHubAppService::class)->makePartial();
    $service->shouldReceive('getInstallationToken')->andReturn(null);

    $organization = Organization::factory()->create(['github_installation_id' => null]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
    ]);

    $webhookId = $service->createRepoWebhook($repo);

    expect($webhookId)->toBeNull();
    expect($repo->fresh()->github_webhook_id)->toBeNull();
});

test('createRepoWebhook returns null for non-github url', function () {
    $service = mockGitHubAppService();

    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://gitlab.com/acme/my-repo.git',
    ]);

    $webhookId = $service->createRepoWebhook($repo);

    expect($webhookId)->toBeNull();
});

test('deleteRepoWebhook removes webhook and clears id', function () {
    Http::fake([
        'api.github.com/repos/acme/my-repo/hooks/777' => Http::response(null, 204),
    ]);

    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
        'github_webhook_id' => 777,
    ]);

    $service = mockGitHubAppService();
    $result = $service->deleteRepoWebhook($repo);

    expect($result)->toBeTrue();
    expect($repo->fresh()->github_webhook_id)->toBeNull();
});

test('deleteRepoWebhook succeeds when webhook already gone (404)', function () {
    Http::fake([
        'api.github.com/repos/acme/my-repo/hooks/777' => Http::response(null, 404),
    ]);

    $organization = Organization::factory()->create(['github_installation_id' => 100]);
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'url' => 'https://github.com/acme/my-repo.git',
        'github_webhook_id' => 777,
    ]);

    $service = mockGitHubAppService();
    $result = $service->deleteRepoWebhook($repo);

    expect($result)->toBeTrue();
    expect($repo->fresh()->github_webhook_id)->toBeNull();
});

test('deleteRepoWebhook returns false when no webhook id set', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $repo = Repo::factory()->create([
        'project_id' => $project->id,
        'github_webhook_id' => null,
    ]);

    $service = new GitHubAppService;
    expect($service->deleteRepoWebhook($repo))->toBeFalse();
});

test('repo gitHubOwnerAndRepo parses https url', function () {
    $repo = new Repo(['url' => 'https://github.com/acme/my-repo.git']);

    expect($repo->gitHubOwnerAndRepo())->toBe([
        'owner' => 'acme',
        'repo' => 'my-repo',
    ]);
});

test('repo gitHubOwnerAndRepo parses ssh url', function () {
    $repo = new Repo(['url' => 'git@github.com:acme/my-repo.git']);

    expect($repo->gitHubOwnerAndRepo())->toBe([
        'owner' => 'acme',
        'repo' => 'my-repo',
    ]);
});

test('repo gitHubOwnerAndRepo returns null for non-github url', function () {
    $repo = new Repo(['url' => 'https://gitlab.com/acme/my-repo.git']);

    expect($repo->gitHubOwnerAndRepo())->toBeNull();
});

test('repo hasWebhook returns true when webhook id set', function () {
    $repo = new Repo(['github_webhook_id' => 123]);

    expect($repo->hasWebhook())->toBeTrue();
});

test('repo hasWebhook returns false when webhook id is null', function () {
    $repo = new Repo;

    expect($repo->hasWebhook())->toBeFalse();
});
