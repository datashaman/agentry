<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentType extends Model
{
    /** @use HasFactory<\Database\Factories\AgentTypeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'instructions',
        'tools',
        'default_model',
        'default_provider',
        'default_temperature',
        'default_max_steps',
        'default_max_tokens',
        'default_timeout',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'default_temperature' => 'float',
        ];
    }

    /**
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
