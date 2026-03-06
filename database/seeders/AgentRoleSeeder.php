<?php

namespace Database\Seeders;

use App\Models\AgentRole;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class AgentRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::where('slug', 'agentry-hq')->firstOrFail();

        $agentRoles = [
            [
                'name' => 'Monitoring',
                'slug' => 'monitoring',
                'description' => 'Monitors system health and alerts on anomalies',
                'instructions' => 'You monitor production systems for anomalies, errors, and degraded performance. When you detect issues, file bugs with clear reproduction steps and severity.',
            ],
            [
                'name' => 'Triage',
                'slug' => 'triage',
                'description' => 'Classifies and prioritizes incoming issues',
                'instructions' => 'You triage incoming bugs and issues. Deduplicate, classify by type and severity, set priority, and assign to appropriate backlogs.',
            ],
            [
                'name' => 'Planning',
                'slug' => 'planning',
                'description' => 'Decomposes work into epics, stories, and tasks',
                'instructions' => 'You decompose work into epics, stories, and tasks. Groom backlogs, refine acceptance criteria, estimate story points, and schedule work into sprints.',
            ],
            [
                'name' => 'Spec Critic',
                'slug' => 'spec-critic',
                'description' => 'Reviews specifications for completeness and clarity',
                'instructions' => 'You review story specifications before grooming. Identify vague acceptance criteria, missing edge cases, internal contradictions, and unclear requirements.',
            ],
            [
                'name' => 'Design Critic',
                'slug' => 'design-critic',
                'description' => 'Reviews design decisions for quality and consistency',
                'instructions' => 'You review implementation design before coding. Identify over-engineering, wrong abstractions, missed patterns, and architectural inconsistencies.',
            ],
            [
                'name' => 'Coding',
                'slug' => 'coding',
                'description' => 'Implements code changes for stories and bugs',
                'instructions' => 'You implement code changes for stories and bugs. Create branches, write code following project conventions, open pull requests, and address review feedback.',
            ],
            [
                'name' => 'Review',
                'slug' => 'review',
                'description' => 'Reviews pull requests for code quality',
                'instructions' => 'You review pull requests for code quality, correctness, and adherence to conventions. Approve or request changes with clear, constructive feedback.',
            ],
            [
                'name' => 'Test',
                'slug' => 'test',
                'description' => 'Runs QA and verifies implementations',
                'instructions' => 'You run QA and verify implementations. Execute tests, verify acceptance criteria, check for regressions, and sign off when quality criteria are met.',
            ],
            [
                'name' => 'Release',
                'slug' => 'release',
                'description' => 'Manages merging, deployment, and release processes',
                'instructions' => 'You manage merging, deployment, and release processes. Merge approved PRs, trigger deployments, verify releases, and clean up branches and worktrees.',
            ],
            [
                'name' => 'Ops',
                'slug' => 'ops',
                'description' => 'Executes operational tasks and infrastructure changes',
                'instructions' => 'You execute operational tasks and infrastructure changes. Classify ops requests, perform direct actions when safe, generate runbooks for complex work, and verify outcomes.',
            ],
        ];

        foreach ($agentRoles as $agentRole) {
            AgentRole::updateOrCreate(
                ['organization_id' => $organization->id, 'slug' => $agentRole['slug']],
                array_merge($agentRole, ['organization_id' => $organization->id]),
            );
        }
    }
}
