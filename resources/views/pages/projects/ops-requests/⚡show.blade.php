<?php

use App\Models\OpsRequest;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ops Request Detail')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public OpsRequest $opsRequest;

    public function mount(): void
    {
        $this->opsRequest->load([
            'assignedAgent',
            'stories',
            'bugs',
            'runbooks.steps',
            'hitlEscalations.raisedByAgent',
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

    {{-- Header --}}
    <div data-test="ops-request-header">
        <flux:heading size="xl">{{ $opsRequest->title }}</flux:heading>
        <div class="mt-3 flex flex-wrap gap-3">
            <flux:badge size="sm" variant="pill" data-test="ops-request-status">{{ str_replace('_', ' ', $opsRequest->status) }}</flux:badge>
            <flux:badge size="sm" variant="pill" data-test="ops-request-category">{{ $opsRequest->category }}</flux:badge>
            @php
                $riskColors = ['critical' => 'red', 'high' => 'amber', 'medium' => 'blue', 'low' => 'zinc'];
            @endphp
            <flux:badge size="sm" variant="pill" :color="$riskColors[$opsRequest->risk_level] ?? 'zinc'" data-test="ops-request-risk-level">{{ $opsRequest->risk_level }}</flux:badge>
            <flux:badge size="sm" variant="pill" data-test="ops-request-execution-type">{{ $opsRequest->execution_type }}</flux:badge>
            @if ($opsRequest->environment)
                <flux:badge size="sm" variant="pill" data-test="ops-request-environment">{{ $opsRequest->environment }}</flux:badge>
            @endif
            @if ($opsRequest->assignedAgent)
                <flux:badge size="sm" variant="pill" data-test="ops-request-agent">{{ $opsRequest->assignedAgent->name }}</flux:badge>
            @endif
        </div>
    </div>

    {{-- Description --}}
    @if ($opsRequest->description)
        <div data-test="ops-request-description">
            <flux:heading size="lg">{{ __('Description') }}</flux:heading>
            <flux:text class="mt-2 whitespace-pre-wrap">{{ $opsRequest->description }}</flux:text>
        </div>
    @endif

    {{-- Linked Stories --}}
    @if ($opsRequest->stories->isNotEmpty())
        <div data-test="ops-request-stories">
            <flux:heading size="lg">{{ __('Linked Stories') }}</flux:heading>
            <ul class="mt-2 space-y-1">
                @foreach ($opsRequest->stories as $story)
                    <li>
                        <a href="{{ route('projects.stories.show', [$project, $story]) }}" class="text-sm hover:underline" wire:navigate>
                            {{ $story->title }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Linked Bugs --}}
    @if ($opsRequest->bugs->isNotEmpty())
        <div data-test="ops-request-bugs">
            <flux:heading size="lg">{{ __('Linked Bugs') }}</flux:heading>
            <ul class="mt-2 space-y-1">
                @foreach ($opsRequest->bugs as $bug)
                    <li>
                        <a href="{{ route('projects.bugs.show', [$project, $bug]) }}" class="text-sm hover:underline" wire:navigate>
                            {{ $bug->title }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Runbooks --}}
    @if ($opsRequest->runbooks->isNotEmpty())
        <div data-test="ops-request-runbooks">
            <flux:heading size="lg">{{ __('Runbooks') }}</flux:heading>
            <div class="mt-2 space-y-4">
                @foreach ($opsRequest->runbooks as $runbook)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="runbook-item">
                        <div class="flex items-center gap-2">
                            <flux:text class="font-medium">{{ $runbook->title }}</flux:text>
                            <flux:badge size="sm" variant="pill">{{ $runbook->status }}</flux:badge>
                        </div>
                        @if ($runbook->description)
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $runbook->description }}</flux:text>
                        @endif
                        @if ($runbook->steps->isNotEmpty())
                            <div class="mt-3 space-y-2" data-test="runbook-steps">
                                @foreach ($runbook->steps as $step)
                                    <div class="flex items-start gap-3 rounded border border-zinc-100 p-3 dark:border-zinc-700" data-test="runbook-step">
                                        <flux:badge size="sm" variant="pill" class="shrink-0">{{ $step->position }}</flux:badge>
                                        <div class="flex-1">
                                            <flux:text class="text-sm">{{ $step->instruction }}</flux:text>
                                            <div class="mt-1 flex items-center gap-2">
                                                @php
                                                    $stepStatusColors = ['completed' => 'green', 'executing' => 'blue', 'failed' => 'red', 'skipped' => 'zinc', 'pending' => 'zinc'];
                                                @endphp
                                                <flux:badge size="sm" variant="pill" :color="$stepStatusColors[$step->status] ?? 'zinc'" data-test="step-status">{{ $step->status }}</flux:badge>
                                                @if ($step->executed_by)
                                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('by :who', ['who' => $step->executed_by]) }}</flux:text>
                                                @endif
                                            </div>
                                        </div>
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
    @if ($opsRequest->hitlEscalations->isNotEmpty())
        <div data-test="ops-request-escalations">
            <flux:heading size="lg">{{ __('HITL Escalations') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($opsRequest->hitlEscalations as $escalation)
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
</div>
