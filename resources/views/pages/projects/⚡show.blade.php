<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Project')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function reposCount(): int
    {
        return $this->project->repos()->count();
    }

    #[Computed]
    public function opsRequestsCount(): int
    {
        return $this->project->opsRequests()->count();
    }

    #[Computed]
    public function workItemProvider(): ?string
    {
        return $this->project->work_item_provider;
    }

}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
            @if ($project->description)
                <flux:text class="mt-1">{{ $project->description }}</flux:text>
            @else
                <flux:text class="mt-1">{{ $project->slug }}</flux:text>
            @endif
        </div>
        <a href="{{ route('projects.edit', $project) }}" wire:navigate>
            <flux:button icon="pencil" data-test="edit-project-button">{{ __('Edit') }}</flux:button>
        </a>
    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4" data-test="summary-stats">
        <a href="{{ route('projects.ops-requests.index', $project) }}" wire:navigate class="rounded-xl border border-zinc-200 p-6 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
            <flux:text class="text-sm font-medium">{{ __('Ops Requests') }}</flux:text>
            <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100" data-test="ops-requests-count">
                {{ $this->opsRequestsCount }}
            </div>
        </a>

        <a href="{{ route('projects.repos.index', $project) }}" wire:navigate class="rounded-xl border border-zinc-200 p-6 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
            <flux:text class="text-sm font-medium">{{ __('Repositories') }}</flux:text>
            <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100" data-test="repos-count">
                {{ $this->reposCount }}
            </div>
        </a>

        <a href="{{ route('projects.action-logs.index', $project) }}" wire:navigate class="rounded-xl border border-zinc-200 p-6 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50" data-test="action-logs-link">
            <flux:text class="text-sm font-medium">{{ __('Action Logs') }}</flux:text>
            <flux:text class="mt-2 block text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('View') }}</flux:text>
        </a>

        <a href="{{ route('projects.work-items.index', $project) }}" wire:navigate class="rounded-xl border border-zinc-200 p-6 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50" data-test="work-items-link">
            <flux:text class="text-sm font-medium">{{ __('Work Items') }}</flux:text>
            <flux:text class="mt-2 block text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ __('View') }}</flux:text>
        </a>
    </div>

</div>
