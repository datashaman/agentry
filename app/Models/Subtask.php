<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subtask extends Model
{
    /** @use HasFactory<\Database\Factories\SubtaskFactory> */
    use HasFactory;

    /**
     * Valid statuses for subtasks.
     *
     * @var list<string>
     */
    public const STATUSES = ['pending', 'in_progress', 'completed'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'title',
        'description',
        'status',
        'position',
    ];

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
