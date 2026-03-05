<?php

namespace App\Models;

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
     * @return MorphMany<ActionLog, $this>
     */
    public function actionLogs(): MorphMany
    {
        return $this->morphMany(ActionLog::class, 'work_item');
    }

    public function hasBlockingCritique(): bool
    {
        return $this->critiques()
            ->where('severity', 'blocking')
            ->where('disposition', 'pending')
            ->exists();
    }
}
