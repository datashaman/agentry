<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Epics')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function epics(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->epics()
            ->withCount('stories')
            ->orderBy('priority')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ __('Epics') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Browse epics for :project.', ['project' => $project->name]) }}</flux:text>
    </div>

    @if ($this->epics->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Epics') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No epics found for this project.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Title') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Priority') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Stories') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->epics as $epic)
                        <tr class="border-b border-zinc-200 dark:border-zinc-700" data-test="epic-row" wire:key="epic-{{ $epic->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.stories.index', ['project' => $project, 'epic' => $epic->id]) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="epic-link">
                                    {{ $epic->title }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill">{{ $epic->status }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>P{{ $epic->priority }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $epic->stories_count }} {{ Str::plural('story', $epic->stories_count) }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
