<?php

use App\Models\Bug;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Bug Detail')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Bug $bug;

    public function mount(): void
    {
        $this->bug->load([
            'linkedStory',
            'assignedAgent',
            'critiques.agent',
            'hitlEscalations.raisedByAgent',
            'changeSets.pullRequests',
        ]);
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

    {{-- Critiques --}}
    @if ($bug->critiques->isNotEmpty())
        <div data-test="bug-critiques">
            <flux:heading size="lg">{{ __('Critiques') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($bug->critiques as $critique)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="critique-item">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" variant="pill">{{ $critique->critic_type }}</flux:badge>
                            <flux:badge size="sm" variant="pill">{{ $critique->severity }}</flux:badge>
                            <flux:badge size="sm" variant="pill">{{ $critique->disposition }}</flux:badge>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Rev :rev', ['rev' => $critique->revision]) }}</flux:text>
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
                        </div>
                        <flux:text class="mt-1 text-sm">{{ $escalation->reason }}</flux:text>
                        @if ($escalation->raisedByAgent)
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Raised by :agent', ['agent' => $escalation->raisedByAgent->name]) }}</flux:text>
                        @endif
                        @if ($escalation->isResolved())
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Resolution: :resolution', ['resolution' => $escalation->resolution]) }}</flux:text>
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
