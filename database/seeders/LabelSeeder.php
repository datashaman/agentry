<?php

namespace Database\Seeders;

use App\Models\Label;
use App\Models\Project;
use Illuminate\Database\Seeder;

class LabelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::firstOrFail();

        $labels = [
            ['name' => 'bug', 'color' => '#dc2626'],
            ['name' => 'feature', 'color' => '#2563eb'],
            ['name' => 'enhancement', 'color' => '#7c3aed'],
            ['name' => 'documentation', 'color' => '#059669'],
            ['name' => 'priority:high', 'color' => '#ea580c'],
        ];

        foreach ($labels as $label) {
            Label::factory()->create(array_merge($label, [
                'project_id' => $project->id,
            ]));
        }
    }
}
