<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Repo;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Worktree>
 */
class WorktreeFactory extends Factory
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
            'repo_id' => $repo->id,
            'branch_id' => $branch->id,
            'work_item_id' => null,
            'work_item_type' => null,
            'path' => '/worktrees/'.fake()->slug(3),
            'status' => 'active',
            'last_activity_at' => now(),
            'interrupted_at' => null,
            'interrupted_reason' => null,
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

    public function interrupted(?string $reason = null): static
    {
        return $this->state(fn () => [
            'status' => 'interrupted',
            'interrupted_at' => now(),
            'interrupted_reason' => $reason ?? 'Agent disconnected',
        ]);
    }

    public function stale(): static
    {
        return $this->state(fn () => [
            'status' => 'stale',
            'last_activity_at' => now()->subDays(7),
        ]);
    }
}
