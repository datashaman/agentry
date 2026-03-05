<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Dependency extends Model
{
    /** @use HasFactory<\Database\Factories\DependencyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blocker_type',
        'blocker_id',
        'blocked_type',
        'blocked_id',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function blocker(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function blocked(): MorphTo
    {
        return $this->morphTo();
    }
}
