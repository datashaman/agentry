<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class HitlEscalation extends Model
{
    /** @use HasFactory<\Database\Factories\HitlEscalationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'work_item_id',
        'work_item_type',
        'raised_by_agent_id',
        'trigger_type',
        'trigger_class',
        'agent_confidence',
        'reason',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agent_confidence' => 'float',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function workItem(): MorphTo
    {
        return $this->morphTo('work_item');
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function raisedByAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'raised_by_agent_id');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
