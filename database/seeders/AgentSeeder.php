<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Team;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::firstOrFail();
        $codingType = AgentType::where('slug', 'coding')->where('organization_id', $team->organization_id)->firstOrFail();

        Agent::factory()->create([
            'agent_type_id' => $codingType->id,
            'team_id' => $team->id,
            'name' => 'Pinky Coder',
            'model' => 'claude-sonnet-4-6',
            'confidence_threshold' => 0.8,
            'tools' => ['code_editor', 'terminal', 'browser'],
            'capabilities' => ['write_code', 'run_tests', 'create_pr'],
            'status' => 'idle',
        ]);
    }
}
