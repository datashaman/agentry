<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunbookStep extends Model
{
    /** @use HasFactory<\Database\Factories\RunbookStepFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'runbook_id',
        'position',
        'instruction',
        'status',
        'executed_by',
        'executed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Runbook, $this>
     */
    public function runbook(): BelongsTo
    {
        return $this->belongsTo(Runbook::class);
    }
}
