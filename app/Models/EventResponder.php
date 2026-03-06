<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventResponder extends Model
{
    /** @use HasFactory<\Database\Factories\EventResponderFactory> */
    use HasFactory;

    public const WORK_ITEM_TYPES = [
        'ops_request' => OpsRequest::class,
        'work_item' => WorkItem::class,
    ];

    public const AVAILABLE_STATUSES = [
        'ops_request' => ['triaged', 'planning', 'executing', 'verifying', 'new', 'closed_done', 'closed_invalid', 'closed_rejected'],
        'work_item' => ['tracked'],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'agent_role_id',
        'work_item_type',
        'status',
        'instructions',
    ];

    /**
     * @return BelongsTo<AgentRole, $this>
     */
    public function agentRole(): BelongsTo
    {
        return $this->belongsTo(AgentRole::class);
    }
}
