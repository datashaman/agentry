<?php

namespace Database\Seeders;

use App\Models\AgentType;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class AgentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::where('slug', 'pinky-hq')->firstOrFail();

        $agentTypes = [
            ['name' => 'Monitoring', 'slug' => 'monitoring', 'description' => 'Monitors system health and alerts on anomalies'],
            ['name' => 'Triage', 'slug' => 'triage', 'description' => 'Classifies and prioritizes incoming issues'],
            ['name' => 'Planning', 'slug' => 'planning', 'description' => 'Decomposes work into epics, stories, and tasks'],
            ['name' => 'Spec Critic', 'slug' => 'spec-critic', 'description' => 'Reviews specifications for completeness and clarity'],
            ['name' => 'Design Critic', 'slug' => 'design-critic', 'description' => 'Reviews design decisions for quality and consistency'],
            ['name' => 'Coding', 'slug' => 'coding', 'description' => 'Implements code changes for stories and bugs'],
            ['name' => 'Review', 'slug' => 'review', 'description' => 'Reviews pull requests for code quality'],
            ['name' => 'Test', 'slug' => 'test', 'description' => 'Runs QA and verifies implementations'],
            ['name' => 'Release', 'slug' => 'release', 'description' => 'Manages merging, deployment, and release processes'],
            ['name' => 'Ops', 'slug' => 'ops', 'description' => 'Executes operational tasks and infrastructure changes'],
        ];

        foreach ($agentTypes as $agentType) {
            AgentType::factory()->create(array_merge($agentType, ['organization_id' => $organization->id]));
        }
    }
}
