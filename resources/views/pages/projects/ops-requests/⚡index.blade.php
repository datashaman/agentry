<?php

use App\Models\OpsRequest;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Ops Requests')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public Project $project;

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $riskLevel = '';

    #[Url]
    public string $sort = 'created_at';

    #[Url]
    public string $direction = 'desc';

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function opsRequests(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = OpsRequest::query()
            ->where('project_id', $this->project->id)
            ->with(['assignedAgent']);

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->category !== '') {
            $query->where('category', $this->category);
        }

        if ($this->riskLevel !== '') {
            $query->where('risk_level', $this->riskLevel);
        }

        $sortColumn = match ($this->sort) {
            'scheduled_at' => 'scheduled_at',
            default => 'created_at',
        };

        $query->orderBy($sortColumn, $this->direction === 'desc' ? 'desc' : 'asc');

        return $query->paginate(20);
    }

    #[Computed]
    public function statuses(): array
    {
        return array_keys(OpsRequest::TRANSITIONS);
    }

    #[Computed]
    public function categories(): array
    {
        return ['deployment', 'infrastructure', 'config', 'data'];
    }

    #[Computed]
    public function riskLevels(): array
    {
        return ['low', 'medium', 'high', 'critical'];
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedRiskLevel(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = match ($column) {
                'scheduled_at' => 'asc',
                default => 'desc',
            };
        }

        $this->resetPage();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ __('Ops Requests') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Browse ops requests for :project.', ['project' => $project->name]) }}</flux:text>
    </div>

    <div class="flex flex-wrap gap-3" data-test="filters">
        <select wire:model.live="status" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="status-filter">
            <option value="">{{ __('All Statuses') }}</option>
            @foreach ($this->statuses as $s)
                <option value="{{ $s }}">{{ str_replace('_', ' ', ucfirst($s)) }}</option>
            @endforeach
        </select>

        <select wire:model.live="category" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="category-filter">
            <option value="">{{ __('All Categories') }}</option>
            @foreach ($this->categories as $cat)
                <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
            @endforeach
        </select>

        <select wire:model.live="riskLevel" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="risk-level-filter">
            <option value="">{{ __('All Risk Levels') }}</option>
            @foreach ($this->riskLevels as $rl)
                <option value="{{ $rl }}">{{ ucfirst($rl) }}</option>
            @endforeach
        </select>
    </div>

    @if ($this->opsRequests->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Ops Requests') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No ops requests match your filters.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Title') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Category') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Risk Level') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Execution') }}</th>
                        <th class="cursor-pointer px-4 py-3 font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" wire:click="sortBy('scheduled_at')">
                            {{ __('Scheduled') }}
                            @if ($sort === 'scheduled_at')
                                <span>{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="cursor-pointer px-4 py-3 font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200" wire:click="sortBy('created_at')">
                            {{ __('Created') }}
                            @if ($sort === 'created_at')
                                <span>{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->opsRequests as $opsRequest)
                        <tr class="border-b border-zinc-200 dark:border-zinc-700" data-test="ops-request-row" wire:key="ops-request-{{ $opsRequest->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.ops-requests.show', [$project, $opsRequest]) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="ops-request-link">
                                    {{ $opsRequest->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $opsRequest->status) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ ucfirst($opsRequest->category) }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill" :color="match($opsRequest->risk_level) { 'critical' => 'red', 'high' => 'amber', 'medium' => 'blue', default => 'zinc' }">{{ ucfirst($opsRequest->risk_level) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ ucfirst($opsRequest->execution_type) }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $opsRequest->scheduled_at?->format('M j, Y') ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $opsRequest->created_at->format('M j, Y') }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->opsRequests->hasPages())
            <div class="mt-4">
                {{ $this->opsRequests->links() }}
            </div>
        @endif
    @endif
</div>
