<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Repositories')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function repos(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->repos()
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ __('Repositories') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Browse repositories for :project.', ['project' => $project->name]) }}</flux:text>
    </div>

    @if ($this->repos->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Repositories') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No repositories found for this project.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('URL') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Language') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Default Branch') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->repos as $repo)
                        <tr class="border-b border-zinc-200 dark:border-zinc-700" data-test="repo-row" wire:key="repo-{{ $repo->id }}">
                            <td class="px-4 py-3">
                                <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $repo->name }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text class="text-sm">{{ $repo->url }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $repo->primary_language ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $repo->default_branch ?? 'main' }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
