<?php

namespace App\Services;

use App\Models\WorkItem;

class WorkItemClassifier
{
    /**
     * Classify a work item using its project's configured type_labels.
     *
     * Returns the matched type label, or null if classification is not possible.
     */
    public function classify(WorkItem $workItem): ?string
    {
        $project = $workItem->project;

        if (! $project) {
            return null;
        }

        $typeLabels = $project->work_item_provider_config['type_labels'] ?? [];

        if (empty($typeLabels)) {
            return null;
        }

        $provider = $project->work_item_provider;

        if ($provider === 'jira') {
            return $this->classifyJira($workItem, $typeLabels);
        }

        if ($provider === 'github') {
            return $this->classifyGitHub($workItem, $typeLabels);
        }

        return null;
    }

    /**
     * Jira: the work item type is the Jira issue type name.
     *
     * @param  list<string>  $typeLabels
     */
    protected function classifyJira(WorkItem $workItem, array $typeLabels): ?string
    {
        $type = $workItem->type;

        if (! $type) {
            return null;
        }

        foreach ($typeLabels as $label) {
            if (strcasecmp($label, $type) === 0) {
                return $label;
            }
        }

        return null;
    }

    /**
     * GitHub: labels are comma-separated. Find the first label in type_labels.
     *
     * @param  list<string>  $typeLabels
     */
    protected function classifyGitHub(WorkItem $workItem, array $typeLabels): ?string
    {
        $labels = $this->parseGitHubLabels($workItem->type);

        foreach ($labels as $label) {
            foreach ($typeLabels as $typeLabel) {
                if (strcasecmp($label, $typeLabel) === 0) {
                    return $typeLabel;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function parseGitHubLabels(?string $type): array
    {
        if ($type === null || trim($type) === '') {
            return [];
        }

        return array_map(
            fn (string $token): string => trim($token),
            explode(',', $type),
        );
    }
}
