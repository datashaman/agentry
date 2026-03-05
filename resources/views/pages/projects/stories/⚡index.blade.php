<?php

use App\Models\Agent;
use App\Models\Epic;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Story;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Stories')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public Project $project;

    #[Url]
    public string $status = '';

    #[Url]
    public string $epic = '';

    #[Url]
    public string $milestone = '';

    #[Url]
    public string $agent = '';

    #[Url]
    public string $sort = 'priority';

    #[Url]
    public string $direction = 'asc';

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function stories(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Story::query()
            ->whereHas('epic', fn ($q) => $q->where('project_id', $this->project->id))
            ->with(['epic', 'milestone', 'assignedAgent']);

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->epic !== '') {
            $query->where('epic_id', $this->epic);
        }

        if ($this->milestone !== '') {
            $query->where('milestone_id', $this->milestone);
        }

        if ($this->agent !== '') {
            $query->where('assigned_agent_id', $this->agent);
        }

        $sortColumn = match ($this->sort) {
            'due_date' => 'due_date',
            'updated_at' => 'updated_at',
            default => 'priority',
        };

        $query->orderBy($sortColumn, $this->direction === 'desc' ? 'desc' : 'asc');

        return $query->paginate(20);
    }

    #[Computed]
    public function epics(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->epics()->orderBy('title')->get();
    }

    #[Computed]
    public function milestones(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->milestones()->orderBy('title')->get();
    }

    #[Computed]
    public function agents(): \Illuminate\Database\Eloquent\Collection
    {
        return Agent::query()
            ->whereHas('assignedStories', fn ($q) => $q->whereHas('epic', fn ($eq) => $eq->where('project_id', $this->project->id)))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function statuses(): array
    {
        return array_keys(Story::TRANSITIONS);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedEpic(): void
    {
        $this->resetPage();
    }

    public function updatedMilestone(): void
    {
        $this->resetPage();
    }

    public function updatedAgent(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }

        $this->resetPage();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ __('Stories') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Browse stories for :project.', ['project' => $project->name]) }}</flux:text>
    </div>

    <div class="flex flex-wrap gap-3" data-test="filters">
        <select wire:model.live="status" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="status-filter">
            <option value="">{{ __('All Statuses') }}</option>
            @foreach ($this->statuses as $s)
                <option value="{{ $s }}">{{ str_replace('_', ' ', ucfirst($s)) }}</option>
            @endforeach
        </select>

        <select wire:model.live="epic" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="epic-filter">
            <option value="">{{ __('All Epics') }}</option>
            @foreach ($this->epics as $e)
                <option value="{{ $e->id }}">{{ $e->title }}</option>
            @endforeach
        </select>

        <select wire:model.live="milestone" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="milestone-filter">
            <option value="">{{ __('All Milestones') }}</option>
            @foreach ($this->milestones as $m)
                <option value="{{ $m->id }}">{{ $m->title }}</option>
            @endforeach
        </select>

        <select wire:model.live="agent" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="agent-filter">
            <option value="">{{ __('All Agents') }}</option>
            @foreach ($this->agents as $a)
                <option value="{{ $a->id }}">{{ $a->name }}</option>
            @endforeach
        </select>
    </div>

    @if ($this->stories->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Stories') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No stories match your filters.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Title') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="cursor-pointer px-4 py-3 font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" wire:click="sortBy('priority')">
                            {{ __('Priority') }}
                            @if ($sort === 'priority')
                                <span>{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Points') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Agent') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Epic') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Milestone') }}</th>
                        <th class="cursor-pointer px-4 py-3 font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" wire:click="sortBy('due_date')">
                            {{ __('Due Date') }}
                            @if ($sort === 'due_date')
                                <span>{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->stories as $story)
                        <tr class="border-b border-zinc-200 dark:border-zinc-700" data-test="story-row" wire:key="story-{{ $story->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.stories.show', [$project, $story]) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="story-link">
                                    {{ $story->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $story->status) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>P{{ $story->priority }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $story->story_points ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $story->assignedAgent?->name ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $story->epic?->title ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $story->milestone?->title ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $story->due_date?->format('M j, Y') ?? '-' }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->stories->hasPages())
            <div class="mt-4">
                {{ $this->stories->links() }}
            </div>
        @endif
    @endif
</div>
