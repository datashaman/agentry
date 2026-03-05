<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Branch extends Model
{
    /** @use HasFactory<\Database\Factories\BranchFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'repo_id',
        'name',
        'base_branch',
        'work_item_id',
        'work_item_type',
    ];

    /**
     * @return BelongsTo<Repo, $this>
     */
    public function repo(): BelongsTo
    {
        return $this->belongsTo(Repo::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function workItem(): MorphTo
    {
        return $this->morphTo();
    }
}
