<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PullRequest extends Model
{
    /** @use HasFactory<\Database\Factories\PullRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'change_set_id',
        'branch_id',
        'repo_id',
        'agent_id',
        'title',
        'description',
        'status',
        'external_id',
        'external_url',
    ];

    /**
     * @return BelongsTo<ChangeSet, $this>
     */
    public function changeSet(): BelongsTo
    {
        return $this->belongsTo(ChangeSet::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Repo, $this>
     */
    public function repo(): BelongsTo
    {
        return $this->belongsTo(Repo::class);
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
