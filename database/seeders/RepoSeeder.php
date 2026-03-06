<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Repo;
use Illuminate\Database\Seeder;

class RepoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::firstOrFail();

        Repo::factory()->create([
            'project_id' => $project->id,
            'name' => 'agentry-platform',
            'url' => 'https://github.com/datashaman/agentry-platform.git',
            'primary_language' => 'PHP',
            'default_branch' => 'main',
            'tags' => ['backend', 'api'],
        ]);
    }
}
