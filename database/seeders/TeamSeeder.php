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

        Team::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Core Engineering',
            'slug' => 'core-engineering',
        ]);
    }
}
