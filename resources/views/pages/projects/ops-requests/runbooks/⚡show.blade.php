<?php

use App\Models\OpsRequest;
use App\Models\Project;
use App\Models\Runbook;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Runbook')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public OpsRequest $opsRequest;

    public Runbook $runbook;

    public function mount(): void
    {
        if ($this->runbook->ops_request_id !== $this->opsRequest->id) {
            abort(404);
        }
        $this->runbook->load('steps');
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    public function stepStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'green',
            'failed' => 'red',
            'executing' => 'blue',
            'skipped' => 'zinc',
            'pending' => 'amber',
            default => 'zinc',
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $runbook->title }}</flux:heading>
            <flux:text class="mt-1">{{ __('Runbook for :ops', ['ops' => $opsRequest->title]) }}</flux:text>
        </div>
        <a href="{{ route('projects.ops-requests.show', [$project, $opsRequest]) }}" wire:navigate data-test="back-to-ops-request">
            <flux:button variant="ghost">{{ __('Back to Ops Request') }}</flux:button>
        </a>
    </div>

    {{-- Header --}}
    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="runbook-header">
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge size="sm" variant="pill" :color="$this->stepStatusColor($runbook->status)">{{ str_replace('_', ' ', ucfirst($runbook->status)) }}</flux:badge>
        </div>
        @if ($runbook->description)
            <flux:text class="mt-2">{{ $runbook->description }}</flux:text>
        @endif
    </div>

    {{-- Steps --}}
    <div data-test="runbook-steps">
        <flux:heading size="lg">{{ __('Steps') }}</flux:heading>
        @if ($runbook->steps->isEmpty())
            <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">{{ __('No steps defined.') }}</flux:text>
        @else
            <ol class="mt-3 space-y-3 list-none">
                @foreach ($runbook->steps as $step)
                    <li class="flex items-start gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="runbook-step">
                        <flux:badge size="sm" variant="pill" class="shrink-0">{{ $step->position }}</flux:badge>
                        <div class="min-w-0 flex-1">
                            <flux:text class="font-medium">{{ $step->instruction }}</flux:text>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" variant="pill" :color="$this->stepStatusColor($step->status)" data-test="step-status">{{ str_replace('_', ' ', ucfirst($step->status)) }}</flux:badge>
                                @if ($step->executed_by)
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Executed by :who', ['who' => $step->executed_by]) }}</flux:text>
                                @endif
                                @if ($step->executed_at)
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $step->executed_at->format('M j, Y H:i') }}</flux:text>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
