<?php

namespace Database\Seeders;

use App\Models\Epic;
use App\Models\Story;
use Illuminate\Database\Seeder;

class StorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $epic = Epic::firstOrFail();

        Story::factory()->create([
            'epic_id' => $epic->id,
            'title' => 'Implement Core Models',
            'description' => 'Create all foundational domain models for the Agentry platform.',
            'status' => 'backlog',
            'priority' => 1,
            'story_points' => 8,
        ]);
    }
}
