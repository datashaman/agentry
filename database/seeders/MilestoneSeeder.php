<?php

namespace Database\Seeders;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Database\Seeder;

class MilestoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::firstOrFail();

        Milestone::factory()->create([
            'project_id' => $project->id,
            'title' => 'v1.0 - Core Platform',
            'description' => 'Initial release with core models and agent infrastructure.',
            'status' => 'open',
        ]);
    }
}
