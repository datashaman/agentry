<?php

namespace App\Contracts;

use App\Models\Organization;

/**
 * @phpstan-type WorkItem array{
 *     key: string,
 *     title: string,
 *     type: string,
 *     status: string,
 *     priority: string|null,
 *     assignee: string|null,
 *     url: string,
 *     created_at: string|null,
 *     updated_at: string|null,
 * }
 * @phpstan-type ProjectInfo array{
 *     key: string,
 *     name: string,
 * }
 * @phpstan-type IssueType array{
 *     id: string,
 *     name: string,
 * }
 */
interface WorkItemProvider
{
    public function name(): string;

    /**
     * @return list<ProjectInfo>
     */
    public function listProjects(Organization $org): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<WorkItem>
     */
    public function listIssues(Organization $org, string $projectKey, array $filters = []): array;

    /**
     * @return WorkItem|null
     */
    public function getIssue(Organization $org, string $issueKey): ?array;

    /**
     * @return list<IssueType>
     */
    public function listIssueTypes(Organization $org, string $projectKey): array;

    /**
     * @return list<WorkItem>
     */
    public function searchIssues(Organization $org, string $query): array;
}
