<?php

namespace App\Models;

use App\Exceptions\InvalidStatusTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Bug extends Model
{
    /** @use HasFactory<\Database\Factories\BugFactory> */
    use HasFactory;

    /**
     * Allowed status transitions.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'new' => ['triaged', 'closed_duplicate', 'closed_cant_reproduce'],
        'triaged' => ['in_progress'],
        'in_progress' => ['in_review', 'blocked'],
        'in_review' => ['verified', 'in_progress'],
        'verified' => ['released'],
        'released' => ['closed_fixed'],
        'blocked' => ['in_progress'],
        'closed_fixed' => [],
        'closed_duplicate' => [],
        'closed_cant_reproduce' => [],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'linked_story_id',
        'milestone_id',
        'assigned_agent_id',
        'title',
        'description',
        'status',
        'severity',
        'priority',
        'environment',
        'repro_steps',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Story, $this>
     */
    public function linkedStory(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'linked_story_id');
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
     * Transition the bug to a new status, enforcing the state machine rules and invariants.
     */
    public function transitionTo(string $newStatus): self
    {
        $currentStatus = $this->status;
        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowed)) {
            throw new InvalidStatusTransitionException($currentStatus, $newStatus);
        }

        if ($newStatus === 'in_progress') {
            if ($this->hasUnresolvedBlockers()) {
                throw new InvalidStatusTransitionException($currentStatus, $newStatus, 'Unresolved dependencies exist.');
            }

            if ($this->hasUnresolvedEscalation()) {
                throw new InvalidStatusTransitionException($currentStatus, $newStatus, 'Unresolved HITL escalation exists.');
            }
        }

        $this->status = $newStatus;
        $this->save();

        return $this;
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

    /**
     * @return MorphMany<ChangeSet, $this>
     */
    public function changeSets(): MorphMany
    {
        return $this->morphMany(ChangeSet::class, 'work_item');
    }

    /**
     * @return MorphMany<Critique, $this>
     */
    public function critiques(): MorphMany
    {
        return $this->morphMany(Critique::class, 'work_item');
    }

    /**
     * @return MorphMany<HitlEscalation, $this>
     */
    public function hitlEscalations(): MorphMany
    {
        return $this->morphMany(HitlEscalation::class, 'work_item');
    }

    public function hasUnresolvedEscalation(): bool
    {
        return $this->hitlEscalations()
            ->whereNull('resolved_at')
            ->exists();
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'work_item');
    }

    /**
     * @return MorphMany<ActionLog, $this>
     */
    public function actionLogs(): MorphMany
    {
        return $this->morphMany(ActionLog::class, 'work_item');
    }

    public function hasUnresolvedBlockers(): bool
    {
        return $this->blockedByDependencies()
            ->where(function ($query) {
                $query->whereHasMorph('blocker', [Story::class], function ($q) {
                    $q->whereNotIn('status', ['closed_done', 'closed_wont_do']);
                })->orWhereHasMorph('blocker', [self::class], function ($q) {
                    $q->whereNotIn('status', ['closed_fixed', 'closed_duplicate', 'closed_cant_reproduce']);
                });
            })
            ->exists();
    }

    public function hasBlockingCritique(): bool
    {
        return $this->critiques()
            ->where('severity', 'blocking')
            ->where('disposition', 'pending')
            ->exists();
    }
}
