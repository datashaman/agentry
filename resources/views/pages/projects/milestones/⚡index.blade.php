<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Milestones')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public string $statusFilter = '';

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function milestones(): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->project->milestones()
            ->withCount(['stories', 'bugs'])
            ->orderBy('due_date');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Milestones') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Track delivery targets and progress for :project.', ['project' => $project->name]) }}</flux:text>
        </div>
        <a href="{{ route('projects.milestones.create', $project) }}" wire:navigate data-test="create-milestone-button">
            <flux:button variant="primary">{{ __('New Milestone') }}</flux:button>
        </a>
    </div>

    <div class="flex items-center gap-2" data-test="status-filter">
        <flux:button size="sm" :variant="$statusFilter === '' ? 'primary' : 'ghost'" wire:click="$set('statusFilter', '')">{{ __('All') }}</flux:button>
        <flux:button size="sm" :variant="$statusFilter === 'open' ? 'primary' : 'ghost'" wire:click="$set('statusFilter', 'open')">{{ __('Open') }}</flux:button>
        <flux:button size="sm" :variant="$statusFilter === 'active' ? 'primary' : 'ghost'" wire:click="$set('statusFilter', 'active')">{{ __('Active') }}</flux:button>
        <flux:button size="sm" :variant="$statusFilter === 'closed' ? 'primary' : 'ghost'" wire:click="$set('statusFilter', 'closed')">{{ __('Closed') }}</flux:button>
    </div>

    @if ($this->milestones->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Milestones') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No milestones found for this project.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Title') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Due Date') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Stories') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Bugs') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->milestones as $milestone)
                        <a href="{{ route('projects.milestones.show', [$project, $milestone]) }}" wire:navigate class="contents" data-test="milestone-link">
                            <tr class="border-b border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50" data-test="milestone-row" wire:key="milestone-{{ $milestone->id }}">
                                <td class="px-4 py-3">
                                    <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $milestone->title }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="pill">{{ $milestone->status }}</flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $milestone->due_date?->format('M j, Y') ?? '-' }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $milestone->stories_count }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $milestone->bugs_count }}</flux:text>
                                </td>
                            </tr>
                        </a>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
