<?php

use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\Label;
use App\Models\Project;
use App\Models\Story;
use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Story')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Story $story;

    public ?int $resolvingEscalationId = null;

    public string $resolutionNotes = '';

    public string $selectedLabelId = '';

    public function mount(): void
    {
        $this->story->load([
            'epic',
            'milestone',
            'assignedAgent',
            'labels',
            'critiques.agent',
            'tasks.subtasks',
            'hitlEscalations.raisedByAgent',
            'changeSets.pullRequests',
            'blockedByDependencies.blocker',
        ]);
    }

    #[Computed]
    public function availableLabels(): \Illuminate\Database\Eloquent\Collection
    {
        $attachedIds = $this->story->labels->pluck('id')->toArray();

        return Label::query()
            ->where('project_id', $this->project->id)
            ->whereNotIn('id', $attachedIds)
            ->orderBy('name')
            ->get();
    }

    public function attachLabel(): void
    {
        $this->validate([
            'selectedLabelId' => 'required|exists:labels,id',
        ]);

        $label = Label::where('project_id', $this->project->id)->findOrFail($this->selectedLabelId);
        $this->story->labels()->syncWithoutDetaching([$label->id]);
        $this->selectedLabelId = '';
        $this->story->load('labels');
        unset($this->availableLabels);
    }

    public function detachLabel(int $labelId): void
    {
        $this->story->labels()->detach($labelId);
        $this->story->load('labels');
        unset($this->availableLabels);
    }

    public function updateCritiqueDisposition(int $critiqueId, string $disposition): void
    {
        $critique = Critique::findOrFail($critiqueId);
        $critique->update(['disposition' => $disposition]);
        $this->story->load('critiques.agent');
    }

    public function startResolving(int $escalationId): void
    {
        $this->resolvingEscalationId = $escalationId;
        $this->resolutionNotes = '';
    }

    public function cancelResolving(): void
    {
        $this->resolvingEscalationId = null;
        $this->resolutionNotes = '';
    }

    public function resolveEscalation(int $escalationId): void
    {
        $this->validate([
            'resolutionNotes' => 'required|string|min:1',
        ]);

        $escalation = HitlEscalation::findOrFail($escalationId);
        $escalation->update([
            'resolution' => $this->resolutionNotes,
            'resolved_by' => Auth::user()->name,
            'resolved_at' => now(),
        ]);

        $this->resolvingEscalationId = null;
        $this->resolutionNotes = '';
        $this->story->load('hitlEscalations.raisedByAgent');
    }

    public function deferEscalation(int $escalationId): void
    {
        $escalation = HitlEscalation::findOrFail($escalationId);
        $escalation->update([
            'resolution' => 'Deferred',
            'resolved_by' => Auth::user()->name,
            'resolved_at' => now(),
        ]);

        $this->story->load('hitlEscalations.raisedByAgent');
    }

    public function updateTaskStatus(int $taskId, string $status): void
    {
        Validator::validate(
            ['status' => $status],
            ['status' => 'required|string|in:' . implode(',', Task::STATUSES)],
            ['status.in' => __('The selected status is invalid.')]
        );

        $task = Task::where('story_id', $this->story->id)->findOrFail($taskId);
        $task->update(['status' => $status]);
        $this->story->load('tasks.subtasks');
    }

    public function updateSubtaskStatus(int $subtaskId, string $status): void
    {
        Validator::validate(
            ['status' => $status],
            ['status' => 'required|string|in:' . implode(',', Subtask::STATUSES)],
            ['status.in' => __('The selected status is invalid.')]
        );

        $subtask = Subtask::whereHas('task', fn ($q) => $q->where('story_id', $this->story->id))->findOrFail($subtaskId);
        $subtask->update(['status' => $status]);
        $this->story->load('tasks.subtasks');
    }

    public function moveTaskUp(int $taskId): void
    {
        $task = Task::where('story_id', $this->story->id)->findOrFail($taskId);
        $prev = Task::where('story_id', $this->story->id)->where('position', '<', $task->position)->orderBy('position', 'desc')->first();
        if ($prev) {
            [$task->position, $prev->position] = [$prev->position, $task->position];
            $task->save();
            $prev->save();
        }
        $this->story->load('tasks.subtasks');
    }

    public function moveTaskDown(int $taskId): void
    {
        $task = Task::where('story_id', $this->story->id)->findOrFail($taskId);
        $next = Task::where('story_id', $this->story->id)->where('position', '>', $task->position)->orderBy('position', 'asc')->first();
        if ($next) {
            [$task->position, $next->position] = [$next->position, $task->position];
            $task->save();
            $next->save();
        }
        $this->story->load('tasks.subtasks');
    }

    public function moveSubtaskUp(int $subtaskId): void
    {
        $subtask = Subtask::whereHas('task', fn ($q) => $q->where('story_id', $this->story->id))->findOrFail($subtaskId);
        $prev = Subtask::where('task_id', $subtask->task_id)->where('position', '<', $subtask->position)->orderBy('position', 'desc')->first();
        if ($prev) {
            [$subtask->position, $prev->position] = [$prev->position, $subtask->position];
            $subtask->save();
            $prev->save();
        }
        $this->story->load('tasks.subtasks');
    }

    public function moveSubtaskDown(int $subtaskId): void
    {
        $subtask = Subtask::whereHas('task', fn ($q) => $q->where('story_id', $this->story->id))->findOrFail($subtaskId);
        $next = Subtask::where('task_id', $subtask->task_id)->where('position', '>', $subtask->position)->orderBy('position', 'asc')->first();
        if ($next) {
            [$subtask->position, $next->position] = [$next->position, $subtask->position];
            $subtask->save();
            $next->save();
        }
        $this->story->load('tasks.subtasks');
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    {{-- Story Header --}}
    <div data-test="story-header">
        <flux:heading size="xl">{{ $story->title }}</flux:heading>
        <div class="mt-3 flex flex-wrap gap-3">
            <flux:badge size="sm" variant="pill" data-test="story-status">{{ str_replace('_', ' ', $story->status) }}</flux:badge>
            <flux:badge size="sm" variant="pill" data-test="story-priority">P{{ $story->priority }}</flux:badge>
            @if ($story->story_points)
                <flux:badge size="sm" variant="pill" data-test="story-points">{{ $story->story_points }} {{ Str::plural('point', $story->story_points) }}</flux:badge>
            @endif
            @if ($story->due_date)
                <flux:badge size="sm" variant="pill" data-test="story-due-date">{{ $story->due_date->format('M j, Y') }}</flux:badge>
            @endif
            @if ($story->assignedAgent)
                <flux:badge size="sm" variant="pill" data-test="story-agent">{{ $story->assignedAgent->name }}</flux:badge>
            @endif
        </div>
    </div>

    {{-- Description & Acceptance Criteria --}}
    @if ($story->description)
        <div data-test="story-description">
            <flux:heading size="lg">{{ __('Description') }}</flux:heading>
            <flux:text class="mt-2 whitespace-pre-wrap">{{ $story->description }}</flux:text>
        </div>
    @endif

    {{-- Epic & Milestone --}}
    @if ($story->epic || $story->milestone)
        <div class="flex flex-wrap gap-6" data-test="story-context">
            @if ($story->epic)
                <div>
                    <flux:text class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('Epic') }}</flux:text>
                    <flux:text>{{ $story->epic->title }}</flux:text>
                </div>
            @endif
            @if ($story->milestone)
                <div>
                    <flux:text class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('Milestone') }}</flux:text>
                    <flux:text>{{ $story->milestone->title }}</flux:text>
                </div>
            @endif
        </div>
    @endif

    {{-- Labels --}}
    <div data-test="story-labels">
        <flux:heading size="lg">{{ __('Labels') }}</flux:heading>
        <div class="mt-2 flex flex-wrap gap-2">
            @forelse ($story->labels as $label)
                <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium text-white" style="background-color: {{ $label->color }}" data-test="attached-label">
                    {{ $label->name }}
                    <button wire:click="detachLabel({{ $label->id }})" class="ml-1 hover:opacity-75" data-test="detach-label-button" title="{{ __('Remove label') }}">&times;</button>
                </span>
            @empty
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No labels attached.') }}</flux:text>
            @endforelse
        </div>
        @if ($this->availableLabels->isNotEmpty())
            <form wire:submit="attachLabel" class="mt-3 flex items-end gap-2" data-test="attach-label-form">
                <flux:select wire:model="selectedLabelId" :placeholder="__('Select a label...')" data-test="label-select">
                    @foreach ($this->availableLabels as $label)
                        <flux:select.option :value="$label->id">{{ $label->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" size="sm" variant="primary" data-test="attach-label-button">{{ __('Attach') }}</flux:button>
            </form>
        @endif
    </div>

    {{-- Critiques --}}
    @if ($story->critiques->isNotEmpty())
        <div data-test="story-critiques">
            <flux:heading size="lg">{{ __('Critiques') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($story->critiques as $critique)
                    @php
                        $isBlockingPending = $critique->severity === 'blocking' && $critique->disposition === 'pending';
                    @endphp
                    <div class="{{ $isBlockingPending ? 'rounded-lg border-2 border-red-400 bg-red-50 p-4 dark:border-red-600 dark:bg-red-900/20' : 'rounded-lg border border-zinc-200 p-4 dark:border-zinc-700' }}" data-test="critique-item">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" variant="pill">{{ $critique->critic_type }}</flux:badge>
                            @php
                                $severityColors = ['blocking' => 'red', 'major' => 'amber', 'minor' => 'blue', 'suggestion' => 'zinc'];
                            @endphp
                            <flux:badge size="sm" variant="pill" :color="$severityColors[$critique->severity] ?? 'zinc'">{{ $critique->severity }}</flux:badge>
                            @php
                                $dispositionColors = ['pending' => 'amber', 'accepted' => 'green', 'rejected' => 'red', 'deferred' => 'zinc'];
                            @endphp
                            <flux:badge size="sm" variant="pill" :color="$dispositionColors[$critique->disposition] ?? 'zinc'" data-test="critique-disposition">{{ $critique->disposition }}</flux:badge>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Rev :rev', ['rev' => $critique->revision]) }}</flux:text>
                            @if ($critique->agent)
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('by :agent', ['agent' => $critique->agent->name]) }}</flux:text>
                            @endif
                        </div>
                        @if ($critique->issues && count($critique->issues) > 0)
                            <div class="mt-2">
                                <flux:text class="text-sm font-medium">{{ __('Issues') }}</flux:text>
                                <ul class="ml-4 list-disc text-sm">
                                    @foreach ($critique->issues as $issue)
                                        <li>{{ $issue }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($critique->questions && count($critique->questions) > 0)
                            <div class="mt-2">
                                <flux:text class="text-sm font-medium">{{ __('Questions') }}</flux:text>
                                <ul class="ml-4 list-disc text-sm">
                                    @foreach ($critique->questions as $question)
                                        <li>{{ $question }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($critique->recommendations && count($critique->recommendations) > 0)
                            <div class="mt-2">
                                <flux:text class="text-sm font-medium">{{ __('Recommendations') }}</flux:text>
                                <ul class="ml-4 list-disc text-sm">
                                    @foreach ($critique->recommendations as $recommendation)
                                        <li>{{ $recommendation }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        {{-- Disposition Actions --}}
                        <div class="mt-3 flex items-center gap-2" data-test="critique-actions">
                            @if ($critique->disposition !== 'accepted')
                                <flux:button size="sm" variant="primary" wire:click="updateCritiqueDisposition({{ $critique->id }}, 'accepted')" data-test="accept-critique-button">{{ __('Accept') }}</flux:button>
                            @endif
                            @if ($critique->disposition !== 'rejected')
                                <flux:button size="sm" wire:click="updateCritiqueDisposition({{ $critique->id }}, 'rejected')" data-test="reject-critique-button">{{ __('Reject') }}</flux:button>
                            @endif
                            @if ($critique->disposition !== 'deferred')
                                <flux:button size="sm" wire:click="updateCritiqueDisposition({{ $critique->id }}, 'deferred')" data-test="defer-critique-button">{{ __('Defer') }}</flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tasks & Subtasks --}}
    @if ($story->tasks->isNotEmpty())
        <div data-test="story-tasks">
            <flux:heading size="lg">{{ __('Tasks') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($story->tasks as $task)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="task-item" x-data="{ expanded: {{ $task->subtasks->isNotEmpty() ? 'true' : 'false' }} }">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex items-center gap-1">
                                <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="moveTaskUp({{ $task->id }})" :disabled="$loop->first" data-test="task-move-up-button" title="{{ __('Move up') }}" />
                                <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="moveTaskDown({{ $task->id }})" :disabled="$loop->last" data-test="task-move-down-button" title="{{ __('Move down') }}" />
                            </div>
                            @if ($task->subtasks->isNotEmpty())
                                <button type="button" @click="expanded = !expanded" class="flex items-center gap-1 text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100" data-test="task-expand-toggle">
                                    <flux:icon.chevron-down class="size-4" x-show="expanded" />
                                    <flux:icon.chevron-right class="size-4" x-show="!expanded" />
                                    {{ $task->subtasks->count() }} {{ Str::plural('subtask', $task->subtasks->count()) }}
                                </button>
                            @endif
                            <flux:text class="flex-1 font-medium">{{ $task->title }}</flux:text>
                            <flux:badge size="sm" variant="pill">{{ $task->type }}</flux:badge>
                            <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $task->status) }}</flux:badge>
                            <select
                                wire:change="$wire.updateTaskStatus({{ $task->id }}, $event.target.value)"
                                class="rounded-md border border-zinc-300 px-2 py-1 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                                data-test="task-status-select"
                            >
                                @foreach (\App\Models\Task::STATUSES as $s)
                                    <option value="{{ $s }}" @selected($task->status === $s)>{{ __(str_replace('_', ' ', ucfirst($s))) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($task->subtasks->isNotEmpty())
                            <div x-show="expanded" class="ml-6 mt-2 space-y-1 border-l-2 border-zinc-200 pl-4 dark:border-zinc-700">
                                @foreach ($task->subtasks as $subtask)
                                    <div class="flex flex-wrap items-center gap-2 text-sm" data-test="subtask-item">
                                        <div class="flex items-center gap-1">
                                            <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="moveSubtaskUp({{ $subtask->id }})" :disabled="$loop->first" data-test="subtask-move-up-button" title="{{ __('Move up') }}" />
                                            <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="moveSubtaskDown({{ $subtask->id }})" :disabled="$loop->last" data-test="subtask-move-down-button" title="{{ __('Move down') }}" />
                                        </div>
                                        <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $subtask->status) }}</flux:badge>
                                        <span class="flex-1">{{ $subtask->title }}</span>
                                        <select
                                            wire:change="$wire.updateSubtaskStatus({{ $subtask->id }}, $event.target.value)"
                                            class="rounded-md border border-zinc-300 px-2 py-1 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                                            data-test="subtask-status-select"
                                        >
                                            @foreach (\App\Models\Subtask::STATUSES as $s)
                                                <option value="{{ $s }}" @selected($subtask->status === $s)>{{ __(str_replace('_', ' ', ucfirst($s))) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- HITL Escalations --}}
    @if ($story->hitlEscalations->isNotEmpty())
        <div data-test="story-escalations">
            <flux:heading size="lg">{{ __('HITL Escalations') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($story->hitlEscalations as $escalation)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="escalation-item">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" variant="pill">{{ $escalation->trigger_type }}</flux:badge>
                            @if ($escalation->isResolved())
                                <flux:badge size="sm" variant="pill" color="green">{{ __('Resolved') }}</flux:badge>
                            @else
                                <flux:badge size="sm" variant="pill" color="red">{{ __('Unresolved') }}</flux:badge>
                            @endif
                            @if ($escalation->agent_confidence !== null)
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Confidence: :pct%', ['pct' => round($escalation->agent_confidence * 100)]) }}</flux:text>
                            @endif
                        </div>
                        <flux:text class="mt-1 text-sm">{{ $escalation->reason }}</flux:text>
                        @if ($escalation->raisedByAgent)
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Raised by :agent', ['agent' => $escalation->raisedByAgent->name]) }}</flux:text>
                        @endif
                        @if ($escalation->isResolved())
                            <div class="mt-2 rounded bg-green-50 p-3 dark:bg-green-900/20" data-test="escalation-resolution">
                                <flux:text class="text-sm font-medium">{{ __('Resolution: :resolution', ['resolution' => $escalation->resolution]) }}</flux:text>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Resolved by :name on :date', ['name' => $escalation->resolved_by, 'date' => $escalation->resolved_at->format('M j, Y g:i A')]) }}</flux:text>
                            </div>
                        @else
                            {{-- Resolution Actions --}}
                            @if ($resolvingEscalationId === $escalation->id)
                                <form wire:submit="resolveEscalation({{ $escalation->id }})" class="mt-3 space-y-3" data-test="resolution-form">
                                    <flux:textarea wire:model="resolutionNotes" :label="__('Resolution Notes')" :placeholder="__('Describe how this escalation was resolved...')" required data-test="resolution-notes" />
                                    <div class="flex items-center gap-2">
                                        <flux:button type="submit" variant="primary" size="sm" data-test="resolve-button">{{ __('Resolve') }}</flux:button>
                                        <flux:button type="button" size="sm" wire:click="cancelResolving" data-test="cancel-resolve-button">{{ __('Cancel') }}</flux:button>
                                    </div>
                                </form>
                            @else
                                <div class="mt-3 flex items-center gap-2" data-test="escalation-actions">
                                    <flux:button size="sm" variant="primary" wire:click="startResolving({{ $escalation->id }})" data-test="start-resolve-button">{{ __('Resolve') }}</flux:button>
                                    <flux:button size="sm" wire:click="deferEscalation({{ $escalation->id }})" data-test="defer-button">{{ __('Defer') }}</flux:button>
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Change Sets & PRs --}}
    @if ($story->changeSets->isNotEmpty())
        <div data-test="story-changesets">
            <flux:heading size="lg">{{ __('Change Sets') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($story->changeSets as $changeSet)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="changeset-item">
                        <div class="flex items-center gap-2">
                            <flux:text class="font-medium">{{ $changeSet->summary }}</flux:text>
                            <flux:badge size="sm" variant="pill">{{ $changeSet->status }}</flux:badge>
                        </div>
                        @if ($changeSet->pullRequests->isNotEmpty())
                            <ul class="ml-4 mt-2 space-y-1">
                                @foreach ($changeSet->pullRequests as $pr)
                                    <li class="text-sm" data-test="pr-item">
                                        <flux:text>{{ $pr->title ?? __('PR #:number', ['number' => $pr->provider_pr_number ?? $pr->id]) }}</flux:text>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Dependencies --}}
    @if ($story->blockedByDependencies->isNotEmpty())
        <div data-test="story-dependencies">
            <flux:heading size="lg">{{ __('Dependencies') }}</flux:heading>
            <div class="mt-2 space-y-2">
                @foreach ($story->blockedByDependencies as $dependency)
                    <div class="flex items-center gap-2 text-sm" data-test="dependency-item">
                        <flux:text>{{ __('Blocked by:') }}</flux:text>
                        <flux:text class="font-medium">{{ $dependency->blocker?->title ?? __('Unknown') }}</flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
