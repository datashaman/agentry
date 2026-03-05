<?php

use App\Models\Project;
use App\Models\Repo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Repository Detail')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Repo $repo;

    public bool $showDeleteModal = false;

    public function mount(): void
    {
        $this->repo->loadCount(['branches', 'worktrees', 'pullRequests']);
    }

    public function deleteRepo(): void
    {
        $this->repo->delete();

        $this->redirect(route('projects.repos.index', $this->project), navigate: true);
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
    <div class="flex items-center justify-between" data-test="repo-header">
        <div>
            <flux:heading size="xl">{{ $repo->name }}</flux:heading>
            <flux:text class="mt-1">{{ $repo->url }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('projects.repos.edit', [$project, $repo]) }}" wire:navigate data-test="edit-repo-button">
                <flux:button>{{ __('Edit') }}</flux:button>
            </a>
            <flux:modal.trigger name="confirm-repo-deletion">
                <flux:button variant="danger" data-test="delete-repo-button">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Details --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4" data-test="repo-details">
        <div>
            <flux:text class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('Primary Language') }}</flux:text>
            <flux:text class="mt-1">{{ $repo->primary_language ?? '-' }}</flux:text>
        </div>
        <div>
            <flux:text class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('Default Branch') }}</flux:text>
            <flux:text class="mt-1">{{ $repo->default_branch ?? 'main' }}</flux:text>
        </div>
        <div>
            <flux:text class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('URL') }}</flux:text>
            <flux:text class="mt-1 break-all">{{ $repo->url }}</flux:text>
        </div>
        <div>
            <flux:text class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('Tags') }}</flux:text>
            @if ($repo->tags && count($repo->tags) > 0)
                <div class="mt-1 flex flex-wrap gap-1">
                    @foreach ($repo->tags as $tag)
                        <flux:badge size="sm" variant="pill">{{ $tag }}</flux:badge>
                    @endforeach
                </div>
            @else
                <flux:text class="mt-1">-</flux:text>
            @endif
        </div>
    </div>

    {{-- Counts --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3" data-test="repo-counts">
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="branches-count">
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Branches') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $repo->branches_count }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="worktrees-count">
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Worktrees') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $repo->worktrees_count }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="pull-requests-count">
            <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Pull Requests') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $repo->pull_requests_count }}</flux:heading>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-repo-deletion" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this repository?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone. The repository ":name" will be permanently deleted.', ['name' => $repo->name]) }}</flux:text>
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteRepo" data-test="confirm-delete-repo-button">
                    {{ __('Delete Repository') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
