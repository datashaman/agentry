<?php

use App\Models\Bug;
use App\Models\OpsRequest;
use App\Models\Project;
use App\Models\Repo;
use App\Models\Story;
use App\Models\Worktree;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Worktrees')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Repo $repo;

    public function mount(): void
    {
        //
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function worktrees(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repo->worktrees()
            ->with(['workItem', 'branch'])
            ->orderBy('last_activity_at', 'desc')
            ->get();
    }

    public function workItemUrl(Worktree $worktree): ?string
    {
        $item = $worktree->workItem;

        if (! $item) {
            return null;
        }

        return match (get_class($item)) {
            Story::class => route('projects.stories.show', [$this->project, $item]),
            Bug::class => route('projects.bugs.show', [$this->project, $item]),
            OpsRequest::class => route('projects.ops-requests.show', [$this->project, $item]),
            default => null,
        };
    }

    public function workItemTypeLabel(Worktree $worktree): string
    {
        $item = $worktree->workItem;

        return $item ? class_basename(get_class($item)) : '-';
    }

    public function workItemTitle(Worktree $worktree): string
    {
        $item = $worktree->workItem;

        return $item?->title ?? '-';
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'active' => 'green',
            'interrupted' => 'amber',
            'stale' => 'red',
            default => 'zinc',
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Worktrees') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Worktrees for :repo', ['repo' => $repo->name]) }}</flux:text>
        </div>
        <a href="{{ route('projects.repos.show', [$project, $repo]) }}" wire:navigate data-test="back-to-repo-link">
            <flux:button variant="ghost">{{ __('Back to Repository') }}</flux:button>
        </a>
    </div>

    @if ($this->worktrees->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Worktrees') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No worktrees found for this repository.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" data-test="worktrees-table">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Path') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Linked Work Item') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Branch') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Last Activity') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Interrupted At') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Interrupted Reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->worktrees as $worktree)
                        <tr class="border-b border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50" data-test="worktree-row" wire:key="worktree-{{ $worktree->id }}">
                            <td class="px-4 py-3">
                                <flux:text class="font-mono text-sm text-zinc-900 dark:text-zinc-100">{{ $worktree->path }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill" :color="$this->statusColor($worktree->status)" data-test="worktree-status">
                                    {{ ucfirst(str_replace('_', ' ', $worktree->status)) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                @php($url = $this->workItemUrl($worktree))
                                @if ($url)
                                    <a href="{{ $url }}" wire:navigate class="inline-flex items-center gap-1 hover:underline" data-test="work-item-link">
                                        <flux:badge size="sm" variant="pill">{{ $this->workItemTypeLabel($worktree) }}</flux:badge>
                                        <span>{{ $this->workItemTitle($worktree) }}</span>
                                    </a>
                                @else
                                    <flux:text class="text-zinc-500 dark:text-zinc-400">-</flux:text>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:text class="font-mono text-sm">{{ $worktree->branch?->name ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $worktree->last_activity_at?->format('M j, Y H:i') ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $worktree->interrupted_at?->format('M j, Y H:i') ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text class="text-zinc-500 dark:text-zinc-400 max-w-[200px] truncate" title="{{ $worktree->interrupted_reason }}">{{ $worktree->interrupted_reason ?? '-' }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
