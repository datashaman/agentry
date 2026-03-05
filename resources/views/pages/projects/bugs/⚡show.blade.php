<?php

use App\Models\Bug;
use App\Models\Critique;
use App\Models\HitlEscalation;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Bug Detail')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Bug $bug;

    public ?int $resolvingEscalationId = null;

    public string $resolutionNotes = '';

    public string $selectedLabelId = '';

    public function mount(): void
    {
        $this->bug->load([
            'linkedStory',
            'assignedAgent',
            'labels',
            'critiques.agent',
            'hitlEscalations.raisedByAgent',
            'changeSets.pullRequests',
        ]);
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
    @if ($bug->changeSets->isNotEmpty())
        <div data-test="bug-changesets">
            <flux:heading size="lg">{{ __('Change Sets') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($bug->changeSets as $changeSet)
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
</div>
