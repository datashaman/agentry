<?php

namespace App\Listeners;

use App\Events\WorkItemClassified;
use App\Events\WorkItemTracked;
use App\Services\WorkItemClassifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DispatchWorkItemAgentWork implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected WorkItemClassifier $classifier,
    ) {}

    public function handle(WorkItemTracked $event): void
    {
        $workItem = $event->workItem;

        $workItem->update([
            'classified_type' => $this->classifier->classify($workItem),
        ]);

        WorkItemClassified::dispatch($workItem);
    }
}
