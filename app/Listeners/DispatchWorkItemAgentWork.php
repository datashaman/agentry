<?php

namespace App\Listeners;

use App\Events\WorkItemClassified;
use App\Events\WorkItemTracked;
use App\Services\TypeSuggestionService;
use App\Services\WorkItemClassifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DispatchWorkItemAgentWork implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected WorkItemClassifier $classifier,
        protected TypeSuggestionService $suggestionService,
    ) {}

    public function handle(WorkItemTracked $event): void
    {
        $workItem = $event->workItem;
        $project = $workItem->project;

        if (! $project) {
            return;
        }

        $typeLabels = $project->work_item_provider_config['type_labels'] ?? [];

        if (empty($typeLabels)) {
            $this->escalateForTypeLabels($workItem, $project);

            return;
        }

        $classifiedType = $this->classifier->classify($workItem);

        $workItem->update(['classified_type' => $classifiedType]);

        if ($classifiedType !== null && strcasecmp($classifiedType, (string) $workItem->type) === 0) {
            WorkItemClassified::dispatch($workItem);

            return;
        }

        $this->escalateForReclassification($workItem, $classifiedType);
    }

    protected function hasPendingEscalationOfType($workItem, string $triggerType): bool
    {
        return $workItem->hitlEscalations()
            ->where('trigger_type', $triggerType)
            ->whereNull('resolved_at')
            ->exists();
    }

    protected function escalateForTypeLabels($workItem, $project): void
    {
        if ($this->hasPendingEscalationOfType($workItem, 'type_label_suggestion')) {
            return;
        }

        $suggestions = $this->suggestionService->suggestTypeLabels($project);

        $workItem->hitlEscalations()->create([
            'work_item_type' => $workItem::class,
            'trigger_type' => 'type_label_suggestion',
            'reason' => 'No type labels configured for this project. AI suggested type labels for review.',
            'metadata' => [
                'suggested_labels' => $suggestions,
                'project_id' => $project->id,
            ],
        ]);
    }

    protected function escalateForReclassification($workItem, ?string $classifiedType): void
    {
        if ($this->hasPendingEscalationOfType($workItem, 'reclassification')) {
            return;
        }

        $workItem->hitlEscalations()->create([
            'work_item_type' => $workItem::class,
            'trigger_type' => 'reclassification',
            'reason' => $classifiedType
                ? "AI classified this work item as \"{$classifiedType}\" which differs from the provider type \"{$workItem->type}\". Please confirm or revert."
                : "AI could not classify this work item from the configured type labels. Provider type is \"{$workItem->type}\".",
            'metadata' => [
                'classified_type' => $classifiedType,
                'original_type' => $workItem->type,
            ],
        ]);
    }
}
