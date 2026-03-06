<?php

namespace App\Listeners;

use App\Events\BugTransitioned;
use App\Events\OpsRequestTransitioned;
use App\Events\StoryTransitioned;
use App\Jobs\RunAgentWork;
use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Story;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DispatchAgentWork implements ShouldHandleEventsAfterCommit
{
    /**
     * @var array<class-string, string>
     */
    private const MODEL_TO_TYPE = [
        Story::class => 'story',
        Bug::class => 'bug',
        OpsRequest::class => 'ops_request',
    ];

    public function handle(StoryTransitioned|BugTransitioned|OpsRequestTransitioned $event): void
    {
        $workItem = match (true) {
            $event instanceof StoryTransitioned => $event->story,
            $event instanceof BugTransitioned => $event->bug,
            $event instanceof OpsRequestTransitioned => $event->opsRequest,
        };

        $workItemType = self::MODEL_TO_TYPE[get_class($workItem)] ?? null;

        if ($workItemType === null) {
            return;
        }

        $workItem->loadMissing('assignedAgent.agentRole.eventResponders', 'assignedAgent.team');
        $agent = $workItem->assignedAgent;

        if ($agent === null || $agent->agentRole === null) {
            return;
        }

        $responder = $agent->agentRole->eventResponders
            ->where('work_item_type', $workItemType)
            ->where('status', $event->to)
            ->first();

        if ($responder === null) {
            return;
        }

        $team = $agent->team;
        $usesWorkflow = $team && $team->workflow_type !== 'none';

        RunAgentWork::dispatch(
            $agent,
            $usesWorkflow ? $team : null,
            $workItem,
            $responder,
        );
    }
}
