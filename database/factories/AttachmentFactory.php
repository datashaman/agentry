<?php

namespace Database\Factories;

use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $story = Story::factory()->create();

        return [
            'work_item_id' => $story->id,
            'work_item_type' => Story::class,
            'filename' => fake()->word().'.pdf',
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'mime_type' => fake()->randomElement(['application/pdf', 'image/png', 'image/jpeg', 'text/plain']),
            'size' => fake()->numberBetween(1024, 10485760),
        ];
    }

    /**
     * Attach to a Story.
     */
    public function forStory(): static
    {
        return $this->state(fn (array $attributes) => [
            'work_item_id' => Story::factory()->create()->id,
            'work_item_type' => Story::class,
        ]);
    }
}
