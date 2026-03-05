<?php

use App\Models\Attachment;
use App\Models\Bug;
use App\Models\Critique;
use App\Models\Dependency;
use App\Models\HitlEscalation;
use App\Models\Label;
use App\Models\Project;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Bug Detail')] #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;
    public Project $project;

    public Bug $bug;

    public ?int $resolvingEscalationId = null;

    public string $resolutionNotes = '';

    public string $selectedLabelId = '';

    public string $selectedDependencyId = '';

    public $upload = null;

    public function mount(): void
    {
        $this->bug->load([
            'linkedStory',
            'assignedAgent',
            'labels',
            'critiques.agent',
            'hitlEscalations.raisedByAgent',
            'changeSets.pullRequests.branch',
            'changeSets.pullRequests.repo',
            'changeSets.pullRequests.agent',
            'changeSets.pullRequests.reviews.agent',
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
        $dir = 'attachments/bugs/' . $this->bug->id;
        $name = Str::uuid() . '_' . basename($file->getClientOriginalName());
        $stored = $file->storeAs($dir, $name, 'local');

        Attachment::create([
            'work_item_type' => Bug::class,
            'work_item_id' => $this->bug->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $stored,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
        $this->upload = null;
        $this->bug->load('attachments');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $attachment = Attachment::where('work_item_type', Bug::class)
            ->where('work_item_id', $this->bug->id)
            ->findOrFail($attachmentId);
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();
        $this->bug->load('attachments');
    }

    #[Computed]
    public function availableLabels(): \Illuminate\Database\Eloquent\Collection
    {
        $attachedIds = $this->bug->labels->pluck('id')->toArray();

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
        $this->bug->labels()->syncWithoutDetaching([$label->id]);
        $this->selectedLabelId = '';
        $this->bug->load('labels');
        unset($this->availableLabels);
    }

    public function detachLabel(int $labelId): void
    {
        $this->bug->labels()->detach($labelId);
        $this->bug->load('labels');
        unset($this->availableLabels);
    }

    #[Computed]
    public function availableDependencies(): array
    {
        $blockerKeys = $this->bug->blockedByDependencies->map(function ($d) {
            return strtolower(class_basename($d->blocker_type)) . '-' . $d->blocker_id;
        })->toArray();

        $options = [];
        foreach ($this->project->stories()->orderBy('title')->get() as $s) {
            $key = 'story-' . $s->id;
            if (! in_array($key, $blockerKeys)) {
                $options[$key] = __('Story: :title', ['title' => $s->title]);
            }
        }
        foreach ($this->project->bugs()->where('id', '!=', $this->bug->id)->orderBy('title')->get() as $b) {
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
            'blocked_type' => Bug::class,
            'blocked_id' => $this->bug->id,
        ]);
        $this->selectedDependencyId = '';
        $this->bug->load('blockedByDependencies.blocker');
        unset($this->availableDependencies);
    }

    public function removeDependency(int $dependencyId): void
    {
        $dep = Dependency::where('blocked_type', Bug::class)
            ->where('blocked_id', $this->bug->id)
            ->findOrFail($dependencyId);
        $dep->delete();
        $this->bug->load('blockedByDependencies.blocker');
        unset($this->availableDependencies);
    }

    public function updateCritiqueDisposition(int $critiqueId, string $disposition): void
    {
        $critique = Critique::findOrFail($critiqueId);
        $critique->update(['disposition' => $disposition]);
        $this->bug->load('critiques.agent');
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
        $this->bug->load('hitlEscalations.raisedByAgent');
    }

    public function deferEscalation(int $escalationId): void
    {
        $escalation = HitlEscalation::findOrFail($escalationId);
        $escalation->update([
            'resolution' => 'Deferred',
            'resolved_by' => Auth::user()->name,
            'resolved_at' => now(),
        ]);

        $this->bug->load('hitlEscalations.raisedByAgent');
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    {{-- Bug Header --}}
    <div data-test="bug-header">
        <flux:heading size="xl">{{ $bug->title }}</flux:heading>
        <div class="mt-3 flex flex-wrap gap-3">
            <flux:badge size="sm" variant="pill" data-test="bug-status">{{ str_replace('_', ' ', $bug->status) }}</flux:badge>
            @php
                $severityColors = ['critical' => 'red', 'major' => 'amber', 'minor' => 'blue', 'trivial' => 'zinc'];
            @endphp
            <flux:badge size="sm" variant="pill" :color="$severityColors[$bug->severity] ?? 'zinc'" data-test="bug-severity">{{ $bug->severity }}</flux:badge>
            <flux:badge size="sm" variant="pill" data-test="bug-priority">P{{ $bug->priority }}</flux:badge>
            @if ($bug->environment)
                <flux:badge size="sm" variant="pill" data-test="bug-environment">{{ $bug->environment }}</flux:badge>
            @endif
            @if ($bug->assignedAgent)
                <flux:badge size="sm" variant="pill" data-test="bug-agent">{{ $bug->assignedAgent->name }}</flux:badge>
            @endif
        </div>
    </div>

    {{-- Description --}}
    @if ($bug->description)
        <div data-test="bug-description">
            <flux:heading size="lg">{{ __('Description') }}</flux:heading>
            <flux:text class="mt-2 whitespace-pre-wrap">{{ $bug->description }}</flux:text>
        </div>
    @endif

    {{-- Reproduction Steps --}}
    @if ($bug->repro_steps)
        <div data-test="bug-repro-steps">
            <flux:heading size="lg">{{ __('Reproduction Steps') }}</flux:heading>
            <flux:text class="mt-2 whitespace-pre-wrap">{{ $bug->repro_steps }}</flux:text>
        </div>
    @endif

    {{-- Linked Story --}}
    @if ($bug->linkedStory)
        <div data-test="bug-linked-story">
            <flux:text class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('Linked Story') }}</flux:text>
            <a href="{{ route('projects.stories.show', [$project, $bug->linkedStory]) }}" class="text-sm hover:underline" wire:navigate>
                {{ $bug->linkedStory->title }}
            </a>
        </div>
    @endif

    {{-- Labels --}}
    <div data-test="bug-labels">
        <flux:heading size="lg">{{ __('Labels') }}</flux:heading>
        <div class="mt-2 flex flex-wrap gap-2">
            @forelse ($bug->labels as $label)
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
    @if ($bug->critiques->isNotEmpty())
        <div data-test="bug-critiques">
            <flux:heading size="lg">{{ __('Critiques') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($bug->critiques as $critique)
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

    {{-- HITL Escalations --}}
    @if ($bug->hitlEscalations->isNotEmpty())
        <div data-test="bug-escalations">
            <flux:heading size="lg">{{ __('HITL Escalations') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($bug->hitlEscalations as $escalation)
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
    <div data-test="bug-changesets">
        <x-changeset-detail :changeSets="$bug->changeSets" />
    </div>

    {{-- Dependencies --}}
    <div data-test="bug-dependencies">
        <flux:heading size="lg">{{ __('Dependencies') }}</flux:heading>
        <div class="mt-2 space-y-2">
            @forelse ($bug->blockedByDependencies as $dependency)
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
    <div data-test="bug-attachments">
        <flux:heading size="lg">{{ __('Attachments') }}</flux:heading>
        <div class="mt-2 space-y-2">
            @forelse ($bug->attachments as $attachment)
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
