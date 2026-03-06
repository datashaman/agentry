<?php

namespace App\Services;

use App\Contracts\WorkItemProvider;
use App\Models\Project;

class WorkItemProviderManager
{
    public function __construct(
        protected JiraService $jira,
        protected GitHubIssuesService $github,
    ) {}

    public function resolve(Project $project): ?WorkItemProvider
    {
        return match ($project->work_item_provider) {
            'jira' => $this->jira,
            'github' => $this->github,
            default => null,
        };
    }
}
