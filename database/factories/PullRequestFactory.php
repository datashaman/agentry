<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\ChangeSet;
use App\Models\Repo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PullRequest>
 */
class PullRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $repo = Repo::factory()->create();
        $branch = Branch::factory()->create(['repo_id' => $repo->id]);

        return [
            'change_set_id' => ChangeSet::factory(),
            'branch_id' => $branch->id,
            'repo_id' => $repo->id,
            'agent_id' => Agent::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['open', 'approved', 'merged', 'closed']),
            'external_id' => (string) fake()->randomNumber(5),
            'external_url' => fake()->url(),
        ];
    }
}
