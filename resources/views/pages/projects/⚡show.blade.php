<?php

use App\Models\Project;
use App\Models\Story;
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
    public function storiesCount(): int
    {
        return $this->project->stories()->count();
    }

    #[Computed]
    public function bugsCount(): int
    {
        return $this->project->bugs()->count();
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
    public function epics(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->epics()
            ->withCount('stories')
            ->orderBy('priority')
            ->get();
    }

    #[Computed]
    public function activeStoriesByStatus(): array
    {
        $activeStatuses = ['backlog', 'spec_critique', 'refined', 'sprint_planned', 'design_critique', 'in_development', 'in_review', 'staging', 'blocked'];

        return Story::query()
            ->whereHas('epic', fn ($q) => $q->where('project_id', $this->project->id))
            ->whereIn('status', $activeStatuses)
            ->with('epic')
            ->orderBy('priority')
            ->get()
            ->groupBy('status')
            ->all();
    }

    #[Computed]
    public function milestones(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->milestones()
            ->whereIn('status', ['open', 'active'])
            ->orderBy('due_date')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ $project->name }}</flux:heading>
        @if ($project->description)
            <flux:text class="mt-1">{{ $project->description }}</flux:text>
        @else
            <flux:text class="mt-1">{{ $project->slug }}</flux:text>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4" data-test="summary-stats">
        <a href="{{ route('projects.stories.index', $project) }}" wire:navigate class="rounded-xl border border-zinc-200 p-6 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
            <flux:text class="text-sm font-medium">{{ __('Stories') }}</flux:text>
            <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100" data-test="stories-count">
                {{ $this->storiesCount }}
            </div>
        </a>

        <a href="{{ route('projects.bugs.index', $project) }}" wire:navigate class="rounded-xl border border-zinc-200 p-6 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
            <flux:text class="text-sm font-medium">{{ __('Bugs') }}</flux:text>
            <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100" data-test="bugs-count">
                {{ $this->bugsCount }}
            </div>
        </a>

        <a href="{{ route('projects.ops-requests.index', $project) }}" wire:navigate class="rounded-xl border border-zinc-200 p-6 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
            <flux:text class="text-sm font-medium">{{ __('Ops Requests') }}</flux:text>
            <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100" data-test="ops-requests-count">
                {{ $this->opsRequestsCount }}
            </div>
        </a>

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:text class="text-sm font-medium">{{ __('Repositories') }}</flux:text>
            <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100" data-test="repos-count">
                {{ $this->reposCount }}
            </div>
        </div>
    </div>

    @if ($this->epics->isNotEmpty())
        <div>
            <flux:heading size="lg">{{ __('Epics') }}</flux:heading>
            <div class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->epics as $epic)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-test="epic-card">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ $epic->title }}</flux:heading>
                            <flux:badge size="sm" variant="pill">{{ $epic->status }}</flux:badge>
                        </div>
                        <flux:text class="mt-2 text-sm">{{ $epic->stories_count }} {{ Str::plural('story', $epic->stories_count) }}</flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if (count($this->activeStoriesByStatus) > 0)
        <div>
            <flux:heading size="lg">{{ __('Active Stories') }}</flux:heading>
            <div class="mt-3 space-y-4">
                @foreach ($this->activeStoriesByStatus as $status => $stories)
                    <div data-test="status-group">
                        <flux:heading size="sm" class="mb-2">{{ str_replace('_', ' ', ucfirst($status)) }} ({{ count($stories) }})</flux:heading>
                        <div class="space-y-2">
                            @foreach ($stories as $story)
                                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="story-row">
                                    <div>
                                        <flux:text class="font-medium">{{ $story->title }}</flux:text>
                                        <flux:text class="text-xs text-zinc-500">{{ $story->epic?->title }}</flux:text>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if ($story->story_points)
                                            <flux:badge size="sm" variant="pill">{{ $story->story_points }} pts</flux:badge>
                                        @endif
                                        <flux:badge size="sm" variant="pill">P{{ $story->priority }}</flux:badge>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->milestones->isNotEmpty())
        <div>
            <flux:heading size="lg">{{ __('Milestones') }}</flux:heading>
            <div class="mt-3 space-y-2">
                @foreach ($this->milestones as $milestone)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="milestone-row">
                        <div>
                            <flux:text class="font-medium">{{ $milestone->title }}</flux:text>
                            @if ($milestone->description)
                                <flux:text class="text-sm text-zinc-500">{{ Str::limit($milestone->description, 80) }}</flux:text>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" variant="pill">{{ $milestone->status }}</flux:badge>
                            @if ($milestone->due_date)
                                <flux:text class="text-sm">{{ $milestone->due_date->format('M j, Y') }}</flux:text>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
