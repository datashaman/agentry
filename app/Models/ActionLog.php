<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActionLog extends Model
{
    /** @use HasFactory<\Database\Factories\ActionLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agent_id',
        'work_item_id',
        'work_item_type',
        'action',
        'reasoning',
        'timestamp',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function workItem(): MorphTo
    {
        return $this->morphTo('work_item');
    }
}
