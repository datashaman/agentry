<?php

use App\Models\WorkItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Work Items')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $classifiedType = '';

    #[Url]
    public string $projectId = '';

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function projectIds(): array
    {
        if (! $this->organization) {
            return [];
        }

        return $this->organization->projects()->pluck('id')->all();
    }

    #[Computed]
    public function projects(): array
    {
        if (! $this->organization) {
            return [];
        }

        return $this->organization->projects()->orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed]
    public function workItems(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if (empty($this->projectIds)) {
            return WorkItem::query()->whereRaw('1 = 0')->paginate(20);
        }

        $query = WorkItem::query()
            ->with(['project'])
            ->whereIn('project_id', $this->projectIds);

        if ($this->projectId !== '') {
            $query->where('project_id', $this->projectId);
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('provider_key', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->classifiedType !== '') {
            $query->where('classified_type', $this->classifiedType);
        }

        $query->latest('created_at');

        return $query->paginate(20);
    }

    #[Computed]
    public function statuses(): array
    {
        if (empty($this->projectIds)) {
            return [];
        }

        return WorkItem::query()
            ->whereIn('project_id', $this->projectIds)
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->all();
    }

    #[Computed]
    public function classifiedTypes(): array
    {
        if (empty($this->projectIds)) {
            return [];
        }

        return WorkItem::query()
            ->whereIn('project_id', $this->projectIds)
            ->whereNotNull('classified_type')
            ->distinct()
            ->orderBy('classified_type')
            ->pluck('classified_type')
            ->all();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedClassifiedType(): void
    {
        $this->resetPage();
    }

    public function updatedProjectId(): void
    {
        $this->resetPage();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    <div>
        <flux:heading size="xl">{{ __('Work Items') }}</flux:heading>
        <flux:text class="mt-1">{{ __('All tracked work items across your organization.') }}</flux:text>
    </div>

    <div class="flex flex-wrap gap-3" data-test="filters">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search by title or key...')" size="sm" data-test="search-input" />
        </div>

        <select wire:model.live="projectId" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="project-filter">
            <option value="">{{ __('All Projects') }}</option>
            @foreach ($this->projects as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>

        <select wire:model.live="status" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="status-filter">
            <option value="">{{ __('All Statuses') }}</option>
            @foreach ($this->statuses as $s)
                <option value="{{ $s }}">{{ $s }}</option>
            @endforeach
        </select>

        <select wire:model.live="classifiedType" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="classified-type-filter">
            <option value="">{{ __('All Types') }}</option>
            @foreach ($this->classifiedTypes as $ct)
                <option value="{{ $ct }}">{{ $ct }}</option>
            @endforeach
        </select>
    </div>

    @if ($this->workItems->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Work Items') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No work items match your filters.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Title') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Project') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Type') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Classified') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Assignee') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->workItems as $workItem)
                        <tr class="border-b border-zinc-200 dark:border-zinc-700" data-test="work-item-row" wire:key="work-item-{{ $workItem->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.work-items.show', [$workItem->project, $workItem]) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="work-item-link">
                                    {{ $workItem->title }}
                                </a>
                                <flux:text class="text-xs text-zinc-400">{{ $workItem->provider_key }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.show', $workItem->project) }}" wire:navigate class="hover:underline">
                                    <flux:text>{{ $workItem->project->name }}</flux:text>
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                @if ($workItem->type)
                                    <flux:badge size="sm" variant="pill">{{ $workItem->type }}</flux:badge>
                                @else
                                    <flux:text>-</flux:text>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($workItem->status)
                                    <flux:badge size="sm" variant="outline">{{ $workItem->status }}</flux:badge>
                                @else
                                    <flux:text>-</flux:text>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($workItem->classified_type)
                                    <flux:badge size="sm" variant="pill" color="indigo">{{ $workItem->classified_type }}</flux:badge>
                                @else
                                    <flux:text>-</flux:text>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $workItem->assignee ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $workItem->created_at->format('M j, Y') }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->workItems->hasPages())
            <div class="mt-4">
                {{ $this->workItems->links() }}
            </div>
        @endif
    @endif
</div>
