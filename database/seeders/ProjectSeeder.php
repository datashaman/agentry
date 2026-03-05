<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
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

        Project::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Pinky Platform',
            'slug' => 'pinky-platform',
        ]);
    }
}
