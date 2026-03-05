<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChangeSet extends Model
{
    /** @use HasFactory<\Database\Factories\ChangeSetFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'summary',
        'work_item_id',
        'work_item_type',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function workItem(): MorphTo
    {
        return $this->morphTo();
    }
}
