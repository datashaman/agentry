<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    /** @use HasFactory<\Database\Factories\AgentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agent_type_id',
        'team_id',
        'name',
        'model',
        'confidence_threshold',
        'tools',
        'capabilities',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'capabilities' => 'array',
            'confidence_threshold' => 'float',
        ];
    }

    /**
     * @return BelongsTo<AgentType, $this>
     */
    public function agentType(): BelongsTo
    {
        return $this->belongsTo(AgentType::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<Story, $this>
     */
    public function assignedStories(): HasMany
    {
        return $this->hasMany(Story::class, 'assigned_agent_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_agent_id');
    }

    /**
     * @return HasMany<Bug, $this>
     */
    public function assignedBugs(): HasMany
    {
        return $this->hasMany(Bug::class, 'assigned_agent_id');
    }

    /**
     * @return HasMany<OpsRequest, $this>
     */
    public function assignedOpsRequests(): HasMany
    {
        return $this->hasMany(OpsRequest::class, 'assigned_agent_id');
    }

    /**
     * @return HasMany<PullRequest, $this>
     */
    public function authoredPullRequests(): HasMany
    {
        return $this->hasMany(PullRequest::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
