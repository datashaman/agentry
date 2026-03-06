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
        $team = Team::firstOrFail();
        $codingRole = AgentRole::where('slug', 'coding')->where('organization_id', $team->organization_id)->firstOrFail();

        Agent::factory()->create([
            'agent_role_id' => $codingRole->id,
            'team_id' => $team->id,
            'name' => 'Pinky Coder',
            'model' => 'claude-sonnet-4-6',
            'provider' => 'anthropic',
            'confidence_threshold' => 0.8,
            'status' => 'idle',
        ]);
    }
}
