<?php

namespace Database\Seeders;

use App\Models\Epic;
use App\Models\Project;
use Illuminate\Database\Seeder;

class EpicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::firstOrFail();

        Epic::factory()->create([
            'project_id' => $project->id,
            'title' => 'Platform Foundation',
            'description' => 'Core models and infrastructure for the Agentry platform.',
            'status' => 'open',
            'priority' => 1,
        ]);
    }
}
