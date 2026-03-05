<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Story extends Model
{
    /** @use HasFactory<\Database\Factories\StoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'epic_id',
        'milestone_id',
        'assigned_agent_id',
        'title',
        'description',
        'status',
        'priority',
        'story_points',
        'due_date',
        'spec_revision_count',
        'substantial_change',
        'dev_iteration_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'substantial_change' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Epic, $this>
     */
    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    /**
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    /**
     * @return MorphToMany<Label, $this>
     */
    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return MorphToMany<Label, $this>
     */
    public function labels(): MorphToMany
    {
        return $this->morphToMany(Label::class, 'labelable');
    }

    /**
     * @return MorphMany<Dependency, $this>
     */
    public function blockedByDependencies(): MorphMany
    {
        return $this->morphMany(Dependency::class, 'blocked');
    }

    /**
     * @return MorphMany<Dependency, $this>
     */
    public function blockerForDependencies(): MorphMany
    {
        return $this->morphMany(Dependency::class, 'blocker');
    }

    /**
     * @return HasMany<Bug, $this>
     */
    public function bugs(): HasMany
    {
        return $this->hasMany(Bug::class, 'linked_story_id');
    }

    /**
     * @return BelongsToMany<OpsRequest, $this>
     */
    public function opsRequests(): BelongsToMany
    {
        return $this->belongsToMany(OpsRequest::class)->withTimestamps();
    }

    /**
     * @return MorphMany<Branch, $this>
     */
    public function branches(): MorphMany
    {
        return $this->morphMany(Branch::class, 'work_item');
    }

    /**
     * @return MorphMany<Worktree, $this>
     */
    public function worktrees(): MorphMany
    {
        return $this->morphMany(Worktree::class, 'work_item');
    }

    public function hasUnresolvedBlockers(): bool
    {
        return $this->blockedByDependencies()
            ->where(function ($query) {
                $query->whereHasMorph('blocker', [self::class], function ($q) {
                    $q->whereNotIn('status', ['closed_done', 'closed_wont_do']);
                })->orWhereHasMorph('blocker', [Bug::class], function ($q) {
                    $q->whereNotIn('status', ['closed_fixed', 'closed_duplicate', 'closed_cant_reproduce']);
                });
            })
            ->exists();
    }
}
