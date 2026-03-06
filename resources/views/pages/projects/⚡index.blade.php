<?php

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Projects')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function projects(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if (! $this->organization) {
            return Project::query()->whereRaw('1 = 0')->paginate(15);
        }

        return Project::query()
            ->where('organization_id', $this->organization->id)
            ->withCount(['stories', 'bugs'])
            ->orderBy('updated_at', 'desc')
            ->paginate(15);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />

        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Projects') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Browse projects in your organization.') }}</flux:text>
            </div>
            <a href="{{ route('projects.create') }}" wire:navigate>
                <flux:button variant="primary" icon="plus" data-test="new-project-button">{{ __('New Project') }}</flux:button>
            </a>
        </div>

        @if ($this->projects->isEmpty())
            <div class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <flux:heading size="lg">{{ __('No Projects') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('There are no projects in this organization yet.') }}</flux:text>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Slug') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Stories') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Bugs') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Last Activity') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->projects as $project)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" data-test="project-row" wire:key="project-{{ $project->id }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('projects.show', $project) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="project-link">
                                        {{ $project->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $project->slug }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $project->stories_count }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $project->bugs_count }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $project->updated_at?->diffForHumans() ?? '-' }}</flux:text>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->projects->hasPages())
                <div class="mt-4">
                    {{ $this->projects->links() }}
                </div>
            @endif
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
