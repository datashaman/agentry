<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            OrganizationSeeder::class,
            TeamSeeder::class,
            ProjectSeeder::class,
            AgentTypeSeeder::class,
            SkillSeeder::class,
            AgentSeeder::class,
            EpicSeeder::class,
            LabelSeeder::class,
            MilestoneSeeder::class,
            StorySeeder::class,
            BugSeeder::class,
            OpsRequestSeeder::class,
            RepoSeeder::class,
        ]);

        $organization = Organization::where('slug', 'pinky-hq')->firstOrFail();
        $user->organizations()->attach($organization, ['role' => 'owner']);
    }
}
