<?php

use App\Models\Project;
use App\Services\GitHubIssuesService;
use App\Services\JiraService;
use App\Services\WorkItemProviderManager;

test('resolves jira provider for jira project', function () {
    $project = Project::factory()->create(['work_item_provider' => 'jira']);

    $manager = app(WorkItemProviderManager::class);
    $provider = $manager->resolve($project);

    expect($provider)->toBeInstanceOf(JiraService::class);
});

test('resolves github provider for github project', function () {
    $project = Project::factory()->create(['work_item_provider' => 'github']);

    $manager = app(WorkItemProviderManager::class);
    $provider = $manager->resolve($project);

    expect($provider)->toBeInstanceOf(GitHubIssuesService::class);
});

test('returns null for unconfigured project', function () {
    $project = Project::factory()->create(['work_item_provider' => null]);

    $manager = app(WorkItemProviderManager::class);
    $provider = $manager->resolve($project);

    expect($provider)->toBeNull();
});
