<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Team;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::where('slug', 'core-engineering')->firstOrFail();
        $organizationId = $team->organization_id;

        $roles = AgentRole::where('organization_id', $organizationId)
            ->get()
            ->keyBy('slug');

        $teams = Team::where('organization_id', $organizationId)
            ->get()
            ->keyBy('slug');

        $agents = [
            ['name' => 'Pinky Monitor', 'role' => 'monitoring', 'team' => 'core-engineering', 'model' => 'claude-haiku-4-5', 'schedule' => 'every_5_minutes', 'scheduled_instructions' => 'Check system health. Scan recent error logs, monitor queue depths, and detect anomalies. File bugs for any issues found.'],
            ['name' => 'Pinky Triager', 'role' => 'triage', 'team' => 'ops', 'model' => 'claude-haiku-4-5'],
            ['name' => 'Pinky Planner', 'role' => 'planning', 'team' => 'core-engineering', 'model' => 'claude-opus-4-6'],
            ['name' => 'Pinky Spec Critic', 'role' => 'spec-critic', 'team' => 'quality-gate', 'model' => 'claude-opus-4-6'],
            ['name' => 'Pinky Design Critic', 'role' => 'design-critic', 'team' => 'quality-gate', 'model' => 'claude-opus-4-6'],
            ['name' => 'Pinky Coder', 'role' => 'coding', 'team' => 'development', 'model' => 'claude-sonnet-4-6'],
            ['name' => 'Pinky Reviewer', 'role' => 'review', 'team' => 'development', 'model' => 'claude-opus-4-6'],
            ['name' => 'Pinky Tester', 'role' => 'test', 'team' => 'qa-release', 'model' => 'claude-haiku-4-5'],
            ['name' => 'Pinky Releaser', 'role' => 'release', 'team' => 'qa-release', 'model' => 'claude-haiku-4-5'],
            ['name' => 'Pinky Operator', 'role' => 'ops', 'team' => 'ops', 'model' => 'claude-sonnet-4-6'],
        ];

        $created = [];

        foreach ($agents as $agent) {
            $created[$agent['role']] = Agent::factory()->create(array_filter([
                'agent_role_id' => $roles[$agent['role']]->id,
                'team_id' => $teams[$agent['team']]->id,
                'name' => $agent['name'],
                'model' => $agent['model'],
                'provider' => 'anthropic',
                'confidence_threshold' => 0.8,
                'status' => 'idle',
                'schedule' => $agent['schedule'] ?? null,
                'scheduled_instructions' => $agent['scheduled_instructions'] ?? null,
            ]));
        }

        $teams['quality-gate']->update(['workflow_config' => [
            'agents' => [
                $created['spec-critic']->id,
                $created['design-critic']->id,
            ],
            'cumulative' => true,
        ]]);

        $teams['development']->update(['workflow_config' => [
            'generator_agent_id' => $created['coding']->id,
            'evaluator_agent_id' => $created['review']->id,
            'max_refinements' => 3,
            'min_rating' => 'good',
        ]]);

        $teams['qa-release']->update(['workflow_config' => [
            'agents' => [
                $created['test']->id,
                $created['release']->id,
            ],
            'cumulative' => false,
        ]]);

        $teams['ops']->update(['workflow_config' => [
            'agents' => [
                $created['triage']->id,
                $created['ops']->id,
            ],
            'cumulative' => false,
        ]]);
    }
}
