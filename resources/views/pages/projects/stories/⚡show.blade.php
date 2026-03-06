<?php

use App\Models\Attachment;
use App\Models\Bug;
use App\Models\Critique;
use App\Models\Dependency;
use App\Models\HitlEscalation;
use App\Models\Label;
use App\Models\Project;
use App\Models\Story;
use App\Models\Subtask;
use App\Models\Task;
use App\Services\GitHubAppService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Story')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;
    public Project $project;

    public Story $story;

    public ?int $resolvingEscalationId = null;

    public string $resolutionNotes = '';

    public string $selectedLabelId = '';

    public string $selectedDependencyId = '';

    public $upload = null;

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
            'blockedByDependencies.blocker',
            'attachments',
        ]);
    }

    public function uploadAttachment(): void
    {
        $this->validate([
            'upload' => 'required|file|max:10240',
        ], [
            'upload.required' => __('Please select a file to upload.'),
            'upload.max' => __('The file must not be greater than 10 MB.'),
        ]);

        $file = $this->upload;
        $dir = 'attachments/stories/' . $this->story->id;
        $name = Str::uuid() . '_' . basename($file->getClientOriginalName());
        $stored = $file->storeAs($dir, $name, 'local');

        Attachment::create([
            'work_item_type' => Story::class,
            'work_item_id' => $this->story->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $stored,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
        $this->upload = null;
        $this->story->load('attachments');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $attachment = Attachment::where('work_item_type', Story::class)
            ->where('work_item_id', $this->story->id)
            ->findOrFail($attachmentId);
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();
        $this->story->load('attachments');
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

    #[Computed]
    public function availableDependencies(): array
    {
        $blockerKeys = $this->story->blockedByDependencies->map(function ($d) {
            return strtolower(class_basename($d->blocker_type)) . '-' . $d->blocker_id;
        })->toArray();

        $options = [];
        foreach ($this->project->stories()->where('stories.id', '!=', $this->story->id)->orderBy('stories.title')->get() as $s) {
            $key = 'story-' . $s->id;
            if (! in_array($key, $blockerKeys)) {
                $options[$key] = __('Story: :title', ['title' => $s->title]);
            }
        }
        foreach ($this->project->bugs()->orderBy('title')->get() as $b) {
            $key = 'bug-' . $b->id;
            if (! in_array($key, $blockerKeys)) {
                $options[$key] = __('Bug: :title', ['title' => $b->title]);
            }
        }

        return $options;
    }

    public function attachDependency(): void
    {
        $this->validate([
            'selectedDependencyId' => 'required|string',
        ]);

        if (! preg_match('/^(story|bug)-(\d+)$/', $this->selectedDependencyId, $m)) {
            $this->addError('selectedDependencyId', __('Invalid dependency selection.'));

            return;
        }
        [, $type, $id] = $m;
        $blockerClass = $type === 'story' ? Story::class : Bug::class;

        $blocker = $blockerClass::findOrFail((int) $id);
        if ($type === 'story' && $blocker->epic->project_id !== $this->project->id) {
            $this->addError('selectedDependencyId', __('Dependency must be in the same project.'));

            return;
        }
        if ($type === 'bug' && $blocker->project_id !== $this->project->id) {
            $this->addError('selectedDependencyId', __('Dependency must be in the same project.'));

            return;
        }

        Dependency::firstOrCreate([
            'blocker_type' => $blockerClass,
            'blocker_id' => $blocker->id,
            'blocked_type' => Story::class,
            'blocked_id' => $this->story->id,
        ]);
        $this->selectedDependencyId = '';
        $this->story->load('blockedByDependencies.blocker');
        unset($this->availableDependencies);
    }

    public function removeDependency(int $dependencyId): void
    {
        $dep = Dependency::where('blocked_type', Story::class)
            ->where('blocked_id', $this->story->id)
            ->findOrFail($dependencyId);
        $dep->delete();
        $this->story->load('blockedByDependencies.blocker');
        unset($this->availableDependencies);
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
    public function pullRequests(): array
    {
        $github = app(GitHubAppService::class);
        $branchName = 'feature/story-' . $this->story->id;
        $prs = [];

        foreach ($this->project->repos as $repo) {
            foreach ($github->listPullRequests($repo, $branchName) as $pr) {
                $pr['_repo_name'] = $repo->name;
                $pr['_reviews'] = $github->listPullRequestReviews($repo, $pr['number']);
                $prs[] = $pr;
            }
        }

        return $prs;
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

    {{-- Pull Requests --}}
    @if (count($this->pullRequests) > 0)
        <div data-test="story-pull-requests">
            <flux:heading size="lg">{{ __('Pull Requests') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($this->pullRequests as $pr)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="pr-item">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:text class="font-medium">{{ $pr['title'] }}</flux:text>
                            <flux:badge size="sm" variant="pill">{{ $pr['state'] }}</flux:badge>
                            <flux:badge size="sm" variant="pill">{{ $pr['_repo_name'] }}</flux:badge>
                            <flux:badge size="sm" variant="pill" class="font-mono">{{ $pr['head']['ref'] }}</flux:badge>
                            @if ($pr['html_url'])
                                <a href="{{ $pr['html_url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm text-primary-600 hover:underline dark:text-primary-400" data-test="pr-external-link">
                                    {{ __('Open PR') }} ↗
                                </a>
                            @endif
                        </div>
                        @if (count($pr['_reviews']) > 0)
                            <div class="mt-3 space-y-2">
                                @foreach ($pr['_reviews'] as $review)
                                    <div class="rounded bg-zinc-50 px-3 py-2 dark:bg-zinc-800/50" data-test="review-item">
                                        <div class="flex flex-wrap items-center gap-2 text-sm">
                                            <flux:text class="font-medium">{{ $review['user']['login'] ?? '-' }}</flux:text>
                                            <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $review['state']) }}</flux:badge>
                                            @if ($review['submitted_at'] ?? null)
                                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ \Carbon\Carbon::parse($review['submitted_at'])->format('M j, Y H:i') }}</flux:text>
                                            @endif
                                        </div>
                                        @if ($review['body'] ?? null)
                                            <flux:text class="mt-1 block text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2">{{ Str::limit($review['body'], 200) }}</flux:text>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Dependencies --}}
    <div data-test="story-dependencies">
        <flux:heading size="lg">{{ __('Dependencies') }}</flux:heading>
        <div class="mt-2 space-y-2">
            @forelse ($story->blockedByDependencies as $dependency)
                @php
                    $blocker = $dependency->blocker;
                    $blockerType = $blocker ? class_basename(get_class($blocker)) : 'Unknown';
                    $isResolved = $blocker && match (get_class($blocker)) {
                        \App\Models\Story::class => in_array($blocker->status, ['closed_done', 'closed_wont_do']),
                        \App\Models\Bug::class => in_array($blocker->status, ['closed_fixed', 'closed_duplicate', 'closed_cant_reproduce']),
                        default => false,
                    };
                @endphp
                <div class="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm {{ $isResolved ? 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-zinc-200 dark:border-zinc-700' }}" data-test="dependency-item">
                    <flux:badge size="sm" variant="pill" :color="$isResolved ? 'green' : 'amber'">{{ $isResolved ? __('Resolved') : __('Unresolved') }}</flux:badge>
                    <flux:badge size="sm" variant="pill">{{ $blockerType }}</flux:badge>
                    <flux:badge size="sm" variant="pill">{{ $blocker ? str_replace('_', ' ', $blocker->status) : '-' }}</flux:badge>
                    @if ($blocker)
                        <a href="{{ $blockerType === 'Story' ? route('projects.stories.show', [$project, $blocker]) : route('projects.bugs.show', [$project, $blocker]) }}" wire:navigate class="font-medium hover:underline">{{ $blocker->title }}</a>
                    @else
                        <flux:text class="font-medium">{{ __('Unknown') }}</flux:text>
                    @endif
                    <button type="button" wire:click="removeDependency({{ $dependency->id }})" class="ml-auto text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" data-test="remove-dependency-button" title="{{ __('Remove dependency') }}">&times;</button>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No dependencies.') }}</flux:text>
            @endforelse
        </div>
        @if (count($this->availableDependencies) > 0)
            <form wire:submit="attachDependency" class="mt-3 flex items-end gap-2" data-test="attach-dependency-form">
                <flux:select wire:model="selectedDependencyId" :placeholder="__('Select a story or bug...')" data-test="dependency-select">
                    @foreach ($this->availableDependencies as $key => $label)
                        <flux:select.option :value="$key">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" size="sm" variant="primary" data-test="attach-dependency-button">{{ __('Add') }}</flux:button>
            </form>
        @endif
    </div>

    {{-- Attachments --}}
    <div data-test="story-attachments">
        <flux:heading size="lg">{{ __('Attachments') }}</flux:heading>
        <div class="mt-2 space-y-2">
            @forelse ($story->attachments as $attachment)
                <div class="flex flex-wrap items-center gap-2 rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-test="attachment-item">
                    <flux:text class="font-medium">{{ $attachment->filename }}</flux:text>
                    @if ($attachment->mime_type)
                        <flux:badge size="sm" variant="pill">{{ $attachment->mime_type }}</flux:badge>
                    @endif
                    @if ($attachment->size)
                        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ number_format($attachment->size / 1024, 1) }} KB</flux:text>
                    @endif
                    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $attachment->created_at->format('M j, Y') }}</flux:text>
                    <a href="{{ route('attachments.download', $attachment) }}" class="text-primary-600 hover:underline dark:text-primary-400" data-test="attachment-download-link">{{ __('Download') }}</a>
                    <flux:modal.trigger name="confirm-attachment-deletion-{{ $attachment->id }}">
                        <flux:button size="xs" variant="ghost" class="text-red-600 dark:text-red-400" data-test="delete-attachment-button">{{ __('Delete') }}</flux:button>
                    </flux:modal.trigger>
                </div>
                <flux:modal name="confirm-attachment-deletion-{{ $attachment->id }}" variant="danger">
                    <form wire:submit="deleteAttachment({{ $attachment->id }})">
                        <flux:heading size="lg">{{ __('Delete Attachment') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Are you sure you want to delete :filename?', ['filename' => $attachment->filename]) }}</flux:text>
                        <div class="mt-4 flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>
                            <flux:button type="submit" variant="danger" data-test="confirm-delete-attachment-button">{{ __('Delete') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>
            @empty
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No attachments.') }}</flux:text>
            @endforelse
        </div>
        <form wire:submit="uploadAttachment" class="mt-3 flex items-end gap-2" data-test="upload-attachment-form">
            <flux:field>
                <flux:label>{{ __('File') }}</flux:label>
                <flux:input type="file" wire:model="upload" data-test="attachment-file-input" />
                <flux:error name="upload" />
            </flux:field>
            <flux:button type="submit" size="sm" variant="primary" data-test="upload-attachment-button" wire:loading.attr="disabled">{{ __('Upload') }}</flux:button>
        </form>
    </div>
</div>
