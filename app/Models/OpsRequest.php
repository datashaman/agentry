<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OpsRequest extends Model
{
    /** @use HasFactory<\Database\Factories\OpsRequestFactory> */
    use HasFactory;

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

    public function hasUnresolvedEscalation(): bool
    {
        return $this->hitlEscalations()
            ->whereNull('resolved_at')
            ->exists();
    }
}
