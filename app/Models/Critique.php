<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Critique extends Model
{
    /** @use HasFactory<\Database\Factories\CritiqueFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'work_item_id',
        'work_item_type',
        'agent_id',
        'critic_type',
        'revision',
        'issues',
        'questions',
        'recommendations',
        'severity',
        'disposition',
        'supersedes_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issues' => 'array',
            'questions' => 'array',
            'recommendations' => 'array',
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
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    public function isSuperseded(): bool
    {
        return $this->supersededBy()->exists();
    }
}
