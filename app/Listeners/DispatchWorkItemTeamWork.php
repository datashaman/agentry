<?php

namespace App\Listeners;

use App\Events\BugReported;
use App\Events\OpsRequestCreated;
use App\Events\StoryCreated;
use App\Jobs\RunTeamWork;

class DispatchWorkItemTeamWork
{
    public function handle(BugReported|StoryCreated|OpsRequestCreated $event): void
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
