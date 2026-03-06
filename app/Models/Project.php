<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'instructions',
        'work_item_provider',
        'work_item_provider_config',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_item_provider_config' => 'array',
        ];
    }

    /**
     * @return HasMany<OpsRequest, $this>
     */
    public function opsRequests(): HasMany
    {
        return $this->hasMany(OpsRequest::class);
    }

    /**
     * @return HasMany<Repo, $this>
     */
    public function repos(): HasMany
    {
        return $this->hasMany(Repo::class);
    }

    public function hasTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }
}
