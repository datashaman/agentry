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
        'agent_role_id',
        'team_id',
        'name',
        'model',
        'provider',
        'confidence_threshold',
        'temperature',
        'max_steps',
        'max_tokens',
        'timeout',
        'status',
        'schedule',
        'scheduled_instructions',
        'last_scheduled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence_threshold' => 'float',
            'temperature' => 'float',
            'last_scheduled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AgentRole, $this>
     */
    public function agentRole(): BelongsTo
    {
        return $this->belongsTo(AgentRole::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<OpsRequest, $this>
     */
    public function assignedOpsRequests(): HasMany
    {
        return $this->hasMany(OpsRequest::class, 'assigned_agent_id');
    }

    /**
     * @return HasMany<Critique, $this>
     */
    public function critiques(): HasMany
    {
        return $this->hasMany(Critique::class);
    }

    /**
     * @return HasMany<HitlEscalation, $this>
     */
    public function hitlEscalations(): HasMany
    {
        return $this->hasMany(HitlEscalation::class, 'raised_by_agent_id');
    }

    /**
     * @return HasMany<ActionLog, $this>
     */
    public function actionLogs(): HasMany
    {
        return $this->hasMany(ActionLog::class);
    }
}
