<?php

namespace Database\Factories;

use App\Models\OpsRequest;
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
        $opsRequest = OpsRequest::factory()->create();

        return [
            'work_item_id' => $opsRequest->id,
            'work_item_type' => OpsRequest::class,
            'filename' => fake()->word().'.pdf',
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'mime_type' => fake()->randomElement(['application/pdf', 'image/png', 'image/jpeg', 'text/plain']),
            'size' => fake()->numberBetween(1024, 10485760),
        ];
    }

    public function forOpsRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'work_item_id' => OpsRequest::factory()->create()->id,
            'work_item_type' => OpsRequest::class,
        ]);
    }
}
