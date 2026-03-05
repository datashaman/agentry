<?php

namespace App\Models;

use App\Exceptions\InvalidStatusTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OpsRequest extends Model
{
    /** @use HasFactory<\Database\Factories\OpsRequestFactory> */
    use HasFactory;

    /**
     * Allowed status transitions.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'new' => ['triaged', 'closed_invalid'],
        'triaged' => ['planning'],
        'planning' => ['executing'],
        'executing' => ['verifying'],
        'verifying' => ['closed_done', 'closed_rejected'],
        'closed_done' => [],
        'closed_invalid' => [],
        'closed_rejected' => [],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'assigned_agent_id',
        'title',
        'description',
        'status',
        'category',
        'execution_type',
        'risk_level',
        'environment',
        'scheduled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    /**
     * @return BelongsToMany<Story, $this>
     */
    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Bug, $this>
     */
    public function bugs(): BelongsToMany
    {
        return $this->belongsToMany(Bug::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Repo, $this>
     */
    public function repos(): BelongsToMany
    {
        return $this->belongsToMany(Repo::class)->withTimestamps();
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
     * @return MorphMany<HitlEscalation, $this>
     */
    public function hitlEscalations(): MorphMany
    {
        return $this->morphMany(HitlEscalation::class, 'work_item');
    }

    /**
     * @return MorphMany<ActionLog, $this>
     */
    public function actionLogs(): MorphMany
    {
        return $this->morphMany(ActionLog::class, 'work_item');
    }

    /**
     * @return HasMany<Runbook, $this>
     */
    public function runbooks(): HasMany
    {
        return $this->hasMany(Runbook::class);
    }

    /**
     * Transition the ops request to a new status, enforcing the state machine rules and invariants.
     */
    public function transitionTo(string $newStatus): self
    {
        $currentStatus = $this->status;
        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowed)) {
            throw new InvalidStatusTransitionException($currentStatus, $newStatus);
        }

        if ($newStatus === 'executing' && in_array($this->risk_level, ['high', 'critical'])) {
            if ($this->hasUnresolvedEscalation()) {
                throw new InvalidStatusTransitionException($currentStatus, $newStatus, 'High/critical risk ops requests require HITL approval before executing.');
            }
        }

        $this->status = $newStatus;
        $this->save();

        return $this;
    }

    public function hasUnresolvedEscalation(): bool
    {
        return $this->hitlEscalations()
            ->whereNull('resolved_at')
            ->exists();
    }
}
