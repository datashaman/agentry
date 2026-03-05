<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Worktree extends Model
{
    /** @use HasFactory<\Database\Factories\WorktreeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'repo_id',
        'branch_id',
        'work_item_id',
        'work_item_type',
        'path',
        'status',
        'last_activity_at',
        'interrupted_at',
        'interrupted_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'interrupted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Repo, $this>
     */
    public function repo(): BelongsTo
    {
        return $this->belongsTo(Repo::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function workItem(): MorphTo
    {
        return $this->morphTo();
    }
}
