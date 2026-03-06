<?php

use App\Models\Project;
use App\Services\WorkItemProviderManager;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Work Items')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public string $search = '';

    public array $workItems = [];

    public bool $loading = true;

    public ?string $error = null;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function providerName(): ?string
    {
        return $this->project->work_item_provider;
    }

    #[Computed]
    public function isConfigured(): bool
    {
        return $this->project->work_item_provider !== null;
    }

    public function mount(): void
    {
        $this->loading = $this->isConfigured;
    }

    public function loadWorkItems(): void
    {
        $this->error = null;

        $manager = app(WorkItemProviderManager::class);
        $provider = $manager->resolve($this->project);

        if (! $provider) {
            $this->error = __('Could not resolve work item provider ":provider".', ['provider' => $this->project->work_item_provider]);
            $this->loading = false;

            return;
        }

        $config = $this->project->work_item_provider_config ?? [];
        $projectKey = $config['project_key'] ?? null;

        if (! $projectKey) {
            $this->error = __('No project key configured. Edit the project to set a project key (e.g. owner/repo for GitHub).');
            $this->loading = false;

            return;
        }

        $filters = [];

        if ($this->search !== '') {
            $this->workItems = $provider->searchIssues($this->project->organization, $this->search);
        } else {
            $this->workItems = $provider->listIssues($this->project->organization, $projectKey, $filters);
        }

        if (empty($this->workItems) && $this->search === '') {
            $this->error = __('No work items returned. Check that your GitHub account has access to :key, or install the GitHub App on the organization.', ['key' => $projectKey]);
        }

        $this->loading = false;
    }

    public function searchWorkItems(): void
    {
        $this->loading = true;
        $this->loadWorkItems();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6"
    @if ($loading) wire:init="loadWorkItems" @endif
>
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Work Items') }}</flux:heading>
            <flux:text class="mt-1">
                @if ($this->providerName)
                    {{ __('Work items from :provider for :project.', ['provider' => ucfirst($this->providerName), 'project' => $project->name]) }}
                @else
                    {{ __('Configure a work item provider to see issues here.') }}
                @endif
            </flux:text>
        </div>
    </div>

    @if (! $this->isConfigured)
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Provider Configured') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Configure a work item provider (Jira or GitHub Issues) in project settings.') }}</flux:text>
                <div class="mt-4">
                    <flux:button variant="primary" :href="route('projects.edit', $project)" wire:navigate>
                        {{ __('Configure Provider') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @elseif ($error)
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20" data-test="work-items-error">
            <flux:text class="text-red-800 dark:text-red-200">{{ $error }}</flux:text>
        </div>
    @elseif ($loading)
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:icon name="arrow-path" class="mx-auto size-8 animate-spin text-zinc-400" />
                <flux:text class="mt-3">{{ __('Loading work items...') }}</flux:text>
            </div>
        </div>
    @else
        <div class="flex gap-3">
            <div class="flex-1">
                <flux:input wire:model="search" placeholder="{{ __('Search work items...') }}" icon="magnifying-glass" wire:keydown.enter="searchWorkItems" />
            </div>
            <flux:button wire:click="searchWorkItems">{{ __('Search') }}</flux:button>
        </div>

        @if (empty($workItems))
            <div class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <flux:heading size="lg">{{ __('No Work Items') }}</flux:heading>
                    <flux:text class="mt-2">
                        @if ($search !== '')
                            {{ __('No work items match your search.') }}
                        @else
                            {{ __('No work items found.') }}
                        @endif
                    </flux:text>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Key') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Title') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Type') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Priority') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Assignee') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($workItems as $item)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="wi-{{ $item['key'] }}">
                                <td class="px-4 py-3">
                                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                        {{ $item['key'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $item['title'] }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm">{{ $item['type'] }}</flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="outline">{{ $item['status'] }}</flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $item['priority'] ?? '-' }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $item['assignee'] ?? __('Unassigned') }}</flux:text>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
