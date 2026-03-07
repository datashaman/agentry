<?php

namespace App\Events;

use App\Models\WorkItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkItemClassified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WorkItem $workItem,
    ) {}
}
