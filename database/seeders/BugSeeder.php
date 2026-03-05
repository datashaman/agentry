<?php

namespace Database\Seeders;

use App\Models\Bug;
use App\Models\Project;
use Illuminate\Database\Seeder;

class BugSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::firstOrFail();

        Bug::factory()->create([
            'project_id' => $project->id,
            'title' => 'Sample Login Bug',
            'description' => 'Users intermittently unable to login with valid credentials.',
            'status' => 'new',
            'severity' => 'major',
            'priority' => 3,
            'environment' => 'production',
            'repro_steps' => '1. Navigate to login page. 2. Enter valid credentials. 3. Click submit. 4. Observe intermittent 500 error.',
        ]);
    }
}
