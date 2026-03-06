<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkItem>
 */
class WorkItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = $this->faker->unique()->numberBetween(1, 999);

        return [
            'project_id' => Project::factory(),
            'provider' => 'github',
            'provider_key' => "#{$number}",
            'title' => $this->faker->sentence(4),
            'type' => $this->faker->randomElement(['Issue', 'bug', 'enhancement', 'feature']),
            'status' => $this->faker->randomElement(['open', 'closed']),
            'priority' => null,
            'assignee' => $this->faker->optional()->userName(),
            'url' => "https://github.com/example/repo/issues/{$number}",
        ];
    }
}
