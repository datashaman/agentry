<?php

namespace App\Listeners;

use App\Events\WorkItemClassified;
use App\Jobs\RunTeamWork;

class DispatchWorkItemTeamWork
{
    public function handle(WorkItemClassified $event): void
    {
        $workItem = $event->workItem;
        $workItem->loadMissing('project.teams');

        foreach ($workItem->project->teams as $team) {
            if ($team->workflow_type === 'none') {
                continue;
            }

            RunTeamWork::dispatch($team, $workItem);
        }
    }
}
