<?php

namespace App\Services;

use App\Agents\ChatAgent;
use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Responses\AgentResponse;

class TypeSuggestionService
{
    /**
     * Suggest type labels for a project based on its provider's available types.
     *
     * @return list<string>
     */
    public function suggestTypeLabels(Project $project): array
    {
        $provider = $project->work_item_provider;
        $config = $project->work_item_provider_config ?? [];
        $projectKey = $config['project_key'] ?? '';

        $response = $this->prompt(
            "You are a work item classification expert. Given a project that uses {$provider} as its issue tracker "
            ."(project key: {$projectKey}), suggest a list of type labels that would be useful for classifying work items. "
            .'Common examples for Jira: Bug, Story, Task, Epic, Sub-task. '
            .'Common examples for GitHub: bug, enhancement, feature, documentation, question. '
            .'Return ONLY a JSON array of strings, e.g. ["Bug", "Story", "Task"]. No other text.',
        );

        return $this->parseJsonArray($response->text);
    }

    /**
     * Suggest a classification for a work item given available type labels.
     *
     * @param  list<string>  $typeLabels
     */
    public function suggestClassification(WorkItem $workItem, array $typeLabels): ?string
    {
        $labelsStr = implode(', ', $typeLabels);

        $response = $this->prompt(
            "You are a work item classifier. Given the following work item, classify it into exactly one of these types: [{$labelsStr}]. "
            ."Work item title: \"{$workItem->title}\". "
            ."Work item description: \"{$workItem->description}\". "
            ."Work item provider type: \"{$workItem->type}\". "
            .'Return ONLY the type label string, nothing else.',
        );

        $suggested = trim($response->text);

        foreach ($typeLabels as $label) {
            if (strcasecmp($label, $suggested) === 0) {
                return $label;
            }
        }

        return null;
    }

    protected function prompt(string $prompt): AgentResponse
    {
        $agent = ChatAgent::make(
            instructions: 'You are a helpful assistant that responds with precise, structured data.',
        );

        $user = Auth::user();

        if ($user) {
            $agent->forUser($user);
        }

        return $agent->prompt(
            $prompt,
            provider: config('ai.classification.provider'),
            model: config('ai.classification.model'),
        );
    }

    /**
     * @return list<string>
     */
    protected function parseJsonArray(string $text): array
    {
        $text = trim($text);

        if (preg_match('/\[.*\]/s', $text, $matches)) {
            $text = $matches[0];
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_string'));
    }
}
