<?php

namespace Database\Factories;

use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dependency>
 */
class DependencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blocker_type' => Story::class,
            'blocker_id' => Story::factory(),
            'blocked_type' => Story::class,
            'blocked_id' => Story::factory(),
        ];
    }
}
