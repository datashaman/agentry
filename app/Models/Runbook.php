<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Runbook extends Model
{
    /** @use HasFactory<\Database\Factories\RunbookFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ops_request_id',
        'title',
        'description',
        'status',
    ];

    /**
     * @return BelongsTo<OpsRequest, $this>
     */
    public function opsRequest(): BelongsTo
    {
        return $this->belongsTo(OpsRequest::class);
    }

    /**
     * @return HasMany<RunbookStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RunbookStep::class)->orderBy('position');
    }
}
