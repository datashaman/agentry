<?php

namespace Database\Seeders;

use App\Models\OpsRequest;
use App\Models\Project;
use Illuminate\Database\Seeder;

class OpsRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::firstOrFail();

        OpsRequest::factory()->create([
            'project_id' => $project->id,
            'title' => 'Deploy v1.0 to Production',
            'description' => 'Initial production deployment of the Pinky platform.',
            'status' => 'new',
            'category' => 'deployment',
            'execution_type' => 'supervised',
            'risk_level' => 'high',
            'environment' => 'production',
        ]);
    }
}
