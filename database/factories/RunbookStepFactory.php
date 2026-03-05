<?php

namespace Database\Factories;

use App\Models\Runbook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RunbookStep>
 */
class RunbookStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'runbook_id' => Runbook::factory(),
            'position' => fake()->numberBetween(1, 10),
            'instruction' => fake()->sentence(),
            'status' => fake()->randomElement(['pending', 'executing', 'completed', 'failed', 'skipped']),
            'executed_by' => fake()->optional()->name(),
            'executed_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
