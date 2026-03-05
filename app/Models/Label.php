<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Label extends Model
{
    /** @use HasFactory<\Database\Factories\LabelFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'color',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return MorphToMany<Story, $this>
     */
    public function stories(): MorphToMany
    {
        return $this->morphedByMany(Story::class, 'labelable');
    }

    /**
     * @return MorphToMany<Bug, $this>
     */
    public function bugs(): MorphToMany
    {
        return $this->morphedByMany(Bug::class, 'labelable');
    }
}
