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
        'story' => Story::class,
        'bug' => Bug::class,
        'ops_request' => OpsRequest::class,
    ];

    public const AVAILABLE_STATUSES = [
        'story' => ['spec_critique', 'design_critique', 'in_development', 'in_review', 'backlog', 'refined', 'sprint_planned', 'staging', 'merged', 'deployed', 'blocked', 'closed_done', 'closed_wont_do'],
        'bug' => ['triaged', 'in_progress', 'in_review', 'new', 'verified', 'released', 'blocked', 'closed_fixed', 'closed_duplicate', 'closed_cant_reproduce'],
        'ops_request' => ['triaged', 'planning', 'executing', 'verifying', 'new', 'closed_done', 'closed_invalid', 'closed_rejected'],
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
