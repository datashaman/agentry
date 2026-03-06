<?php

namespace Database\Seeders;

use App\Models\AgentRole;
use App\Models\Organization;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::where('slug', 'agentry-hq')->firstOrFail();

        $skills = [
            [
                'name' => 'Laravel',
                'slug' => 'laravel',
                'description' => 'Laravel conventions, Eloquent, and framework patterns',
                'content' => 'Follow Laravel conventions. Use Eloquent for database access. Prefer relationship methods over raw queries. Use Form Requests for validation. Use named routes with route().',
            ],
            [
                'name' => 'Flux UI',
                'slug' => 'flux-ui',
                'description' => 'Flux UI Free component library for Livewire',
                'content' => "Use flux: components for UI. Prefer flux:input, flux:button, flux:modal, flux:select. Follow Flux UI patterns from the project's existing components.",
            ],
            [
                'name' => 'Pest Testing',
                'slug' => 'pest-testing',
                'description' => 'Pest 4 PHP testing framework',
                'content' => 'Use Pest for tests. Create tests with php artisan make:test --pest. Use expect() for assertions. Use factories for test data.',
            ],
        ];

        $created = [];
        foreach ($skills as $skill) {
            $created[$skill['slug']] = Skill::factory()->create(array_merge($skill, [
                'organization_id' => $organization->id,
            ]));
        }

        $coding = AgentRole::where('slug', 'coding')->where('organization_id', $organization->id)->first();
        $review = AgentRole::where('slug', 'review')->where('organization_id', $organization->id)->first();

        if ($coding) {
            $coding->skills()->attach($created['laravel']->id, ['position' => 0]);
            $coding->skills()->attach($created['flux-ui']->id, ['position' => 1]);
            $coding->skills()->attach($created['pest-testing']->id, ['position' => 2]);
        }

        if ($review) {
            $review->skills()->attach($created['laravel']->id, ['position' => 0]);
            $review->skills()->attach($created['pest-testing']->id, ['position' => 1]);
        }
    }
}
