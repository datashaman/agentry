<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    /** @use HasFactory<\Database\Factories\SkillFactory> */
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
        'content',
        'source_repo_id',
        'source_path',
        'source_sha',
        'frontmatter_metadata',
        'resource_paths',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frontmatter_metadata' => 'array',
            'resource_paths' => 'array',
        ];
    }

    public function isImported(): bool
    {
        return $this->source_repo_id !== null;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Repo, $this>
     */
    public function sourceRepo(): BelongsTo
    {
        return $this->belongsTo(Repo::class, 'source_repo_id');
    }

    /**
     * @return BelongsToMany<AgentRole, $this>
     */
    public function agentRoles(): BelongsToMany
    {
        return $this->belongsToMany(AgentRole::class, 'agent_role_skill')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
