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
        'github_webhook_id',
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
     * @return array{owner: string, repo: string}|null
     */
    public function gitHubOwnerAndRepo(): ?array
    {
        if (preg_match('#github\.com[/:]([^/]+)/([^/.]+)#', $this->url, $matches)) {
            return ['owner' => $matches[1], 'repo' => $matches[2]];
        }

        return null;
    }

    public function hasWebhook(): bool
    {
        return $this->github_webhook_id !== null;
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
