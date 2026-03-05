<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Bugs')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public Project $project;

    #[Url]
    public string $status = '';

    #[Url]
    public string $severity = '';

    #[Url]
    public string $priority = '';

    #[Url]
    public string $sort = 'severity';

    #[Url]
    public string $direction = 'asc';

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function bugs(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Bug::query()
            ->where('project_id', $this->project->id)
            ->with(['linkedStory', 'assignedAgent']);

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->severity !== '') {
            $query->where('severity', $this->severity);
        }

        if ($this->priority !== '') {
            $query->where('priority', $this->priority);
        }

        $sortColumn = match ($this->sort) {
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            default => 'severity',
        };

        if ($sortColumn === 'severity') {
            $dir = $this->direction === 'desc' ? 'desc' : 'asc';
            $query->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'major' THEN 2 WHEN 'minor' THEN 3 WHEN 'trivial' THEN 4 ELSE 5 END {$dir}");
        } else {
            $query->orderBy($sortColumn, $this->direction === 'desc' ? 'desc' : 'asc');
        }

        return $query->paginate(20);
    }

    #[Computed]
    public function statuses(): array
    {
        return array_keys(Bug::TRANSITIONS);
    }

    #[Computed]
    public function severities(): array
    {
        return ['critical', 'major', 'minor', 'trivial'];
    }

    #[Computed]
    public function agents(): \Illuminate\Database\Eloquent\Collection
    {
        return Agent::query()
            ->whereHas('assignedBugs', fn ($q) => $q->where('project_id', $this->project->id))
            ->orderBy('name')
            ->get();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSeverity(): void
    {
        $this->resetPage();
    }

    public function updatedPriority(): void
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
        <flux:heading size="xl">{{ __('Bugs') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Browse bugs for :project.', ['project' => $project->name]) }}</flux:text>
    </div>

    <div class="flex flex-wrap gap-3" data-test="filters">
        <select wire:model.live="status" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="status-filter">
            <option value="">{{ __('All Statuses') }}</option>
            @foreach ($this->statuses as $s)
                <option value="{{ $s }}">{{ str_replace('_', ' ', ucfirst($s)) }}</option>
            @endforeach
        </select>

        <select wire:model.live="severity" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="severity-filter">
            <option value="">{{ __('All Severities') }}</option>
            @foreach ($this->severities as $sev)
                <option value="{{ $sev }}">{{ ucfirst($sev) }}</option>
            @endforeach
        </select>

        <select wire:model.live="priority" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="priority-filter">
            <option value="">{{ __('All Priorities') }}</option>
            @foreach (range(0, 10) as $p)
                <option value="{{ $p }}">P{{ $p }}</option>
            @endforeach
        </select>
    </div>

    @if ($this->bugs->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Bugs') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No bugs match your filters.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Title') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="cursor-pointer px-4 py-3 font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" wire:click="sortBy('severity')">
                            {{ __('Severity') }}
                            @if ($sort === 'severity')
                                <span>{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Priority') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Linked Story') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Agent') }}</th>
                        <th class="cursor-pointer px-4 py-3 font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" wire:click="sortBy('created_at')">
                            {{ __('Created') }}
                            @if ($sort === 'created_at')
                                <span>{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->bugs as $bug)
                        <tr class="border-b border-zinc-200 dark:border-zinc-700" data-test="bug-row" wire:key="bug-{{ $bug->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.bugs.show', [$project, $bug]) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="bug-link">
                                    {{ $bug->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $bug->status) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill" :color="match($bug->severity) { 'critical' => 'red', 'major' => 'amber', 'minor' => 'blue', default => 'zinc' }">{{ ucfirst($bug->severity) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>P{{ $bug->priority }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $bug->linkedStory?->title ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $bug->assignedAgent?->name ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $bug->created_at->format('M j, Y') }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->bugs->hasPages())
            <div class="mt-4">
                {{ $this->bugs->links() }}
            </div>
        @endif
    @endif
</div>
