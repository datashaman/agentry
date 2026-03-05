<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Repo extends Model
{
    /** @use HasFactory<\Database\Factories\RepoFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'url',
        'primary_language',
        'default_branch',
        'tags',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
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
     * @return BelongsToMany<OpsRequest, $this>
     */
    public function opsRequests(): BelongsToMany
    {
        return $this->belongsToMany(OpsRequest::class)->withTimestamps();
    }
}
