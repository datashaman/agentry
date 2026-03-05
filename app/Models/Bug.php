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
}
