<?php

namespace Database\Seeders;

use App\Models\AgentRole;
use App\Models\EventResponder;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class EventResponderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::where('slug', 'agentry-hq')->firstOrFail();

        $roles = AgentRole::where('organization_id', $organization->id)
            ->get()
            ->keyBy('slug');

        $responders = [
            // Stories
            [
                'role' => 'planning',
                'work_item_type' => 'story',
                'status' => 'backlog',
                'instructions' => 'Draft a specification for this story. Define clear acceptance criteria, identify edge cases, and outline implementation considerations. Update the story with your specification.',
            ],
            [
                'role' => 'planning',
                'work_item_type' => 'story',
                'status' => 'refined',
                'instructions' => 'Decompose this refined story into tasks. Break down the implementation into discrete, actionable tasks with clear descriptions and acceptance criteria.',
            ],
            [
                'role' => 'spec-critic',
                'work_item_type' => 'story',
                'status' => 'spec_critique',
                'instructions' => "Review this story's specification. Check for vague acceptance criteria, missing edge cases, and unclear requirements. Create a Critique with your findings.",
            ],
            [
                'role' => 'design-critic',
                'work_item_type' => 'story',
                'status' => 'design_critique',
                'instructions' => 'Review the implementation design for this story. Check for over-engineering, wrong abstractions, and architectural inconsistencies. Create a Critique with your findings.',
            ],
            [
                'role' => 'coding',
                'work_item_type' => 'story',
                'status' => 'in_development',
                'instructions' => 'Implement this story. Read the specification and acceptance criteria. Create a branch, write code following project conventions, write tests, and open a pull request.',
            ],
            [
                'role' => 'review',
                'work_item_type' => 'story',
                'status' => 'in_review',
                'instructions' => 'Review the pull request for this story. Check code quality, test coverage, and adherence to conventions. Approve or request changes.',
            ],

            // Bugs
            [
                'role' => 'triage',
                'work_item_type' => 'bug',
                'status' => 'triaged',
                'instructions' => "Verify this bug's classification. Confirm severity, check for duplicates, and ensure reproduction steps are clear.",
            ],
            [
                'role' => 'coding',
                'work_item_type' => 'bug',
                'status' => 'in_progress',
                'instructions' => 'Fix this bug. Read the reproduction steps, identify the root cause, implement the fix with a regression test, and open a pull request.',
            ],
            [
                'role' => 'review',
                'work_item_type' => 'bug',
                'status' => 'in_review',
                'instructions' => 'Review the pull request for this bug fix. Verify the fix addresses the root cause and includes regression tests.',
            ],

            // Ops Requests
            [
                'role' => 'triage',
                'work_item_type' => 'ops_request',
                'status' => 'triaged',
                'instructions' => 'Review this ops request. Verify classification, assess risk level, and confirm the request is actionable.',
            ],
            [
                'role' => 'ops',
                'work_item_type' => 'ops_request',
                'status' => 'planning',
                'instructions' => 'Create an execution plan for this ops request. Generate a runbook if the operation is complex.',
            ],
            [
                'role' => 'ops',
                'work_item_type' => 'ops_request',
                'status' => 'executing',
                'instructions' => 'Execute this ops request following the established plan. Verify each step and document outcomes.',
            ],
        ];

        foreach ($responders as $responder) {
            EventResponder::factory()->create([
                'agent_role_id' => $roles[$responder['role']]->id,
                'work_item_type' => $responder['work_item_type'],
                'status' => $responder['status'],
                'instructions' => $responder['instructions'],
            ]);
        }
    }
}
