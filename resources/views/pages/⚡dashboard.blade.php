<?php

use App\Models\ActionLog;
use App\Models\Bug;
use App\Models\HitlEscalation;
use App\Models\Story;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] #[Layout('layouts.app')] class extends Component {
    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function projects(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->organization) {
            return collect();
        }

        return $this->organization->projects()->get();
    }

    #[Computed]
    public function projectIds(): array
    {
        return $this->projects->pluck('id')->all();
    }

    #[Computed]
    public function storyCounts(): array
    {
        if (empty($this->projectIds)) {
            return [];
        }

        return Story::query()
            ->whereHas('epic', fn ($q) => $q->whereIn('project_id', $this->projectIds))
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();
    }

    #[Computed]
    public function openBugsCount(): int
    {
        if (empty($this->projectIds)) {
            return 0;
        }

        return Bug::query()
            ->whereIn('project_id', $this->projectIds)
            ->whereNotIn('status', ['closed_fixed', 'closed_duplicate', 'closed_cant_reproduce'])
            ->count();
    }

    #[Computed]
    public function unresolvedEscalationsCount(): int
    {
        if (empty($this->projectIds)) {
            return 0;
        }

        return HitlEscalation::query()
            ->whereNull('resolved_at')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('work_item_type', Story::class)
                        ->whereIn('work_item_id', Story::query()
                            ->whereHas('epic', fn ($eq) => $eq->whereIn('project_id', $this->projectIds))
                            ->select('id'));
                })->orWhere(function ($q) {
                    $q->where('work_item_type', Bug::class)
                        ->whereIn('work_item_id', Bug::query()
                            ->whereIn('project_id', $this->projectIds)
                            ->select('id'));
                })->orWhere(function ($q) {
                    $q->where('work_item_type', \App\Models\OpsRequest::class)
                        ->whereIn('work_item_id', \App\Models\OpsRequest::query()
                            ->whereIn('project_id', $this->projectIds)
                            ->select('id'));
                });
            })
            ->count();
    }

    #[Computed]
    public function activeStoriesCount(): int
    {
        return collect($this->storyCounts)
            ->except(['closed_done', 'closed_wont_do', 'backlog'])
            ->sum();
    }

    #[Computed]
    public function recentActivity(): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($this->projectIds)) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return ActionLog::query()
            ->with(['agent', 'workItem'])
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('work_item_type', Story::class)
                        ->whereIn('work_item_id', Story::query()
                            ->whereHas('epic', fn ($eq) => $eq->whereIn('project_id', $this->projectIds))
                            ->select('id'));
                })->orWhere(function ($q) {
                    $q->where('work_item_type', Bug::class)
                        ->whereIn('work_item_id', Bug::query()
                            ->whereIn('project_id', $this->projectIds)
                            ->select('id'));
                })->orWhere(function ($q) {
                    $q->where('work_item_type', \App\Models\OpsRequest::class)
                        ->whereIn('work_item_id', \App\Models\OpsRequest::query()
                            ->whereIn('project_id', $this->projectIds)
                            ->select('id'));
                });
            })
            ->latest('timestamp')
            ->limit(10)
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
        @if ($this->organization)
            <x-breadcrumbs :organization="$this->organization" />

            <div>
                <flux:heading size="xl">{{ $this->organization->name }}</flux:heading>
                <flux:text class="mt-1">{{ __('Organization overview') }}</flux:text>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:text class="text-sm font-medium">{{ __('Active Stories') }}</flux:text>
                    <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100" data-test="active-stories-count">
                        {{ $this->activeStoriesCount }}
                    </div>
                    @if (count($this->storyCounts) > 0)
                        <div class="mt-3 space-y-1">
                            @foreach ($this->storyCounts as $status => $count)
                                <div class="flex items-center justify-between text-sm">
                                    <flux:text>{{ str_replace('_', ' ', ucfirst($status)) }}</flux:text>
                                    <flux:text class="font-medium">{{ $count }}</flux:text>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:text class="text-sm font-medium">{{ __('Open Bugs') }}</flux:text>
                    <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100" data-test="open-bugs-count">
                        {{ $this->openBugsCount }}
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:text class="text-sm font-medium">{{ __('Unresolved Escalations') }}</flux:text>
                    <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100" data-test="unresolved-escalations-count">
                        {{ $this->unresolvedEscalationsCount }}
                    </div>
                </div>
            </div>

            @if ($this->projects->isNotEmpty())
                <div>
                    <flux:heading size="lg">{{ __('Projects') }}</flux:heading>
                    <div class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this->projects as $project)
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="block rounded-xl border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500" data-test="project-card">
                                <flux:heading size="sm">{{ $project->name }}</flux:heading>
                                <flux:text class="mt-1 text-sm">{{ $project->slug }}</flux:text>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($this->recentActivity->isNotEmpty())
                <div>
                    <flux:heading size="lg">{{ __('Recent Activity') }}</flux:heading>
                    <div class="mt-3 space-y-2">
                        @foreach ($this->recentActivity as $log)
                            <div class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="activity-log-item">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:text class="font-medium text-sm">{{ $log->agent?->name ?? __('Unknown Agent') }}</flux:text>
                                        <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $log->action) }}</flux:badge>
                                    </div>
                                    @if ($log->reasoning)
                                        <flux:text class="mt-1 text-sm">{{ $log->reasoning }}</flux:text>
                                    @endif
                                    <flux:text class="mt-1 text-xs">{{ $log->timestamp?->diffForHumans() }}</flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <div class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <flux:heading size="lg">{{ __('No Organization') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('You are not associated with any organization yet.') }}</flux:text>
                </div>
            </div>
        @endif
    </div>
</div>
