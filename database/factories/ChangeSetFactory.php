<?php

namespace Database\Factories;

use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChangeSet>
 */
class ChangeSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => fake()->randomElement(['draft', 'ready', 'merged', 'reverted']),
            'summary' => fake()->sentence(),
            'work_item_id' => null,
            'work_item_type' => null,
        ];
    }

    public function forStory(?Story $story = null): static
    {
        return $this->state(fn () => [
            'work_item_id' => $story?->id ?? Story::factory(),
            'work_item_type' => Story::class,
        ]);
    }

    public function forBug(?Bug $bug = null): static
    {
        return $this->state(fn () => [
            'work_item_id' => $bug?->id ?? Bug::factory(),
            'work_item_type' => Bug::class,
        ]);
    }

    public function forOpsRequest(?OpsRequest $opsRequest = null): static
    {
        return $this->state(fn () => [
            'work_item_id' => $opsRequest?->id ?? OpsRequest::factory(),
            'work_item_type' => OpsRequest::class,
        ]);
    }
}
