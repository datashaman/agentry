<?php

namespace App\Listeners;

use App\Events\OpsRequestTransitioned;
use App\Jobs\RunAgentWork;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DispatchAgentWork implements ShouldHandleEventsAfterCommit
{
    public function handle(OpsRequestTransitioned $event): void
    {
        $workItem = $event->opsRequest;

        $workItem->loadMissing('assignedAgent.agentRole.eventResponders', 'assignedAgent.team');
        $agent = $workItem->assignedAgent;

        if ($agent === null || $agent->agentRole === null) {
            return;
        }

        $responder = $agent->agentRole->eventResponders
            ->where('work_item_type', 'ops_request')
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
