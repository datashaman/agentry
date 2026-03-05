<?php

use App\Models\Milestone;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Milestone')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Milestone $milestone;

    public function mount(): void
    {
        $this->milestone->loadCount([
            'stories',
            'stories as completed_stories_count' => fn ($q) => $q->where('status', 'closed_done'),
            'bugs',
            'bugs as fixed_bugs_count' => fn ($q) => $q->where('status', 'closed_fixed'),
        ]);
        $this->milestone->load(['stories', 'bugs']);
    }

    public function deleteMilestone(): void
    {
        if ($this->milestone->stories()->count() > 0 || $this->milestone->bugs()->count() > 0) {
            return;
        }

        $this->milestone->delete();

        $this->redirect(route('projects.milestones.index', $this->project), navigate: true);
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
    <div class="flex items-center justify-between" data-test="milestone-header">
        <div>
            <flux:heading size="xl">{{ $milestone->title }}</flux:heading>
            <div class="mt-2 flex items-center gap-3">
                <flux:badge size="sm" variant="pill">{{ $milestone->status }}</flux:badge>
                @if ($milestone->due_date)
                    <flux:text class="text-sm">{{ __('Due :date', ['date' => $milestone->due_date->format('M j, Y')]) }}</flux:text>
                @endif
            </div>
            @if ($milestone->description)
                <flux:text class="mt-2">{{ $milestone->description }}</flux:text>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('projects.milestones.edit', [$project, $milestone]) }}" wire:navigate data-test="edit-milestone-button">
                <flux:button>{{ __('Edit') }}</flux:button>
            </a>
            <flux:modal.trigger name="confirm-milestone-deletion">
                <flux:button variant="danger" data-test="delete-milestone-button">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Progress Summary --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4" data-test="progress-summary">
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Stories') }}</flux:text>
            <flux:heading size="xl">{{ $milestone->stories_count }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Completed Stories') }}</flux:text>
            <flux:heading size="xl">{{ $milestone->completed_stories_count }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Bugs') }}</flux:text>
            <flux:heading size="xl">{{ $milestone->bugs_count }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Fixed Bugs') }}</flux:text>
            <flux:heading size="xl">{{ $milestone->fixed_bugs_count }}</flux:heading>
        </div>
    </div>

    {{-- Stories --}}
    <div data-test="milestone-stories">
        <flux:heading size="lg">{{ __('Stories') }} ({{ $milestone->stories_count }})</flux:heading>
        @if ($milestone->stories->isEmpty())
            <flux:text class="mt-2">{{ __('No stories assigned to this milestone.') }}</flux:text>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Title') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($milestone->stories as $story)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="story-{{ $story->id }}" data-test="story-row">
                                <td class="px-4 py-3">
                                    <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $story->title }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="pill">{{ $story->status }}</flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Bugs --}}
    <div data-test="milestone-bugs">
        <flux:heading size="lg">{{ __('Bugs') }} ({{ $milestone->bugs_count }})</flux:heading>
        @if ($milestone->bugs->isEmpty())
            <flux:text class="mt-2">{{ __('No bugs assigned to this milestone.') }}</flux:text>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Title') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($milestone->bugs as $bug)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="bug-{{ $bug->id }}" data-test="bug-row">
                                <td class="px-4 py-3">
                                    <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $bug->title }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="pill">{{ $bug->status }}</flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-milestone-deletion" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this milestone?') }}</flux:heading>
                @if ($milestone->stories_count > 0 || $milestone->bugs_count > 0)
                    <flux:text class="mt-2 text-red-600">{{ __('This milestone has assigned stories or bugs and cannot be deleted.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('This action cannot be undone. The milestone ":title" will be permanently deleted.', ['title' => $milestone->title]) }}</flux:text>
                @endif
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if ($milestone->stories_count === 0 && $milestone->bugs_count === 0)
                    <flux:button variant="danger" wire:click="deleteMilestone" data-test="confirm-delete-milestone-button">
                        {{ __('Delete Milestone') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
