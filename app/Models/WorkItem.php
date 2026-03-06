<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkItem extends Model
{
    /** @use HasFactory<\Database\Factories\WorkItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'provider',
        'provider_key',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'classified_type',
        'assignee',
        'url',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasOne<Conversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }
}
