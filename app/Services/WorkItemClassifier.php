<?php

namespace App\Services;

use App\Models\WorkItem;

class WorkItemClassifier
{
    /** @var list<string> */
    protected array $opsKeywords = ['ops', 'operations', 'deployment', 'infrastructure', 'incident'];

    /** @var list<string> */
    protected array $bugKeywords = ['bug', 'defect', 'error'];

    public function classify(WorkItem $workItem): string
    {
        $tokens = $this->tokenize($workItem->type);

        foreach ($tokens as $token) {
            foreach ($this->opsKeywords as $keyword) {
                if (str_contains($token, $keyword)) {
                    return 'ops_request';
                }
            }
        }

        foreach ($tokens as $token) {
            foreach ($this->bugKeywords as $keyword) {
                if (str_contains($token, $keyword)) {
                    return 'bug';
                }
            }
        }

        return 'story';
    }

    /**
     * @return list<string>
     */
    protected function tokenize(?string $type): array
    {
        if ($type === null || trim($type) === '') {
            return [];
        }

        return array_map(
            fn (string $token): string => strtolower(trim($token)),
            explode(',', $type),
        );
    }
}
