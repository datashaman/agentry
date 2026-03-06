<?php

namespace App\Listeners;

use App\Events\BugReported;
use App\Events\OpsRequestCreated;
use App\Events\StoryCreated;
use App\Events\WorkItemTracked;
use App\Services\WorkItemClassifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DispatchWorkItemAgentWork implements ShouldHandleEventsAfterCommit
{
    /** @var array<string, class-string> */
    protected array $eventMap = [
        'bug' => BugReported::class,
        'story' => StoryCreated::class,
        'ops_request' => OpsRequestCreated::class,
    ];

    public function __construct(
        protected WorkItemClassifier $classifier,
    ) {}

    public function handle(WorkItemTracked $event): void
    {
        $workItem = $event->workItem;

        $classifiedType = $this->classifier->classify($workItem);

        $workItem->update([
            'classified_type' => $classifiedType,
        ]);

        $eventClass = $this->eventMap[$classifiedType];

        $eventClass::dispatch($workItem);
    }
}
