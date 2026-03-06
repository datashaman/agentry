<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::firstOrCreate(
            ['slug' => 'pinky-hq'],
            ['name' => 'Pinky HQ'],
        );

        $teams = [
            [
                'name' => 'Core Engineering',
                'slug' => 'core-engineering',
                'workflow_type' => 'none',
            ],
            [
                'name' => 'Quality Gate',
                'slug' => 'quality-gate',
                'workflow_type' => 'chain',
            ],
            [
                'name' => 'Development',
                'slug' => 'development',
                'workflow_type' => 'evaluator_optimizer',
            ],
            [
                'name' => 'QA & Release',
                'slug' => 'qa-release',
                'workflow_type' => 'chain',
            ],
            [
                'name' => 'Ops',
                'slug' => 'ops',
                'workflow_type' => 'chain',
            ],
        ];

        foreach ($teams as $team) {
            Team::factory()->create(array_merge($team, [
                'organization_id' => $organization->id,
            ]));
        }
    }
}
