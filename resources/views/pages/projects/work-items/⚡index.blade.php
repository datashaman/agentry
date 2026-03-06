<?php

use App\Models\Project;
use App\Services\WorkItemProviderManager;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Work Items')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    #[Session]
    public string $search = '';

    public array $providerIssues = [];

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

    #[Computed]
    public function trackedKeys(): array
    {
        return $this->project->workItems()->pluck('provider_key')->all();
    }

    public function mount(): void
    {
        $this->loading = $this->isConfigured;
    }

    public function loadIssues(): void
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

        if ($this->search !== '') {
            $this->providerIssues = $provider->searchIssues($this->project->organization, $this->search);
        } else {
            $this->providerIssues = $provider->listIssues($this->project->organization, $projectKey);
        }

        $this->loading = false;
    }

    public function searchIssues(): void
    {
        $this->loading = true;
        $this->loadIssues();
    }

    public function trackIssue(string $key): void
    {
        $issue = collect($this->providerIssues)->firstWhere('key', $key);

        if (! $issue) {
            return;
        }

        if ($this->project->workItems()->where('provider_key', $key)->exists()) {
            return;
        }

        $this->project->workItems()->create([
            'provider' => $this->project->work_item_provider,
            'provider_key' => $issue['key'],
            'title' => $issue['title'],
            'type' => $issue['type'],
            'status' => $issue['status'],
            'priority' => $issue['priority'],
            'assignee' => $issue['assignee'],
            'url' => $issue['url'],
        ]);

        unset($this->trackedKeys);
    }

    public function untrackIssue(string $key): void
    {
        $this->project->workItems()->where('provider_key', $key)->delete();

        unset($this->trackedKeys);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6"
    @if ($loading) wire:init="loadIssues" @endif
>
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Work Items') }}</flux:heading>
            <flux:text class="mt-1">
                @if ($this->providerName)
                    {{ __('Track work items from :provider for :project.', ['provider' => ucfirst($this->providerName), 'project' => $project->name]) }}
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
                <flux:input wire:model="search" placeholder="{{ __('Search issues...') }}" icon="magnifying-glass" wire:keydown.enter="searchIssues" />
            </div>
            <flux:button wire:click="searchIssues">{{ __('Search') }}</flux:button>
        </div>

        @if (empty($providerIssues))
            <div class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <flux:heading size="lg">{{ __('No Issues Found') }}</flux:heading>
                    <flux:text class="mt-2">
                        @if ($search !== '')
                            {{ __('No issues match your search.') }}
                        @else
                            {{ __('No open issues found.') }}
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
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Assignee') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($providerIssues as $item)
                            @php
                                $isTracked = in_array($item['key'], $this->trackedKeys);
                            @endphp
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 {{ $isTracked ? 'bg-green-50/50 dark:bg-green-900/10' : '' }}" wire:key="wi-{{ $item['key'] }}" data-test="work-item-row">
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
                                    <flux:text>{{ $item['assignee'] ?? __('Unassigned') }}</flux:text>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($isTracked)
                                        <flux:button size="sm" variant="danger" wire:click="untrackIssue('{{ $item['key'] }}')" data-test="untrack-button">
                                            {{ __('Untrack') }}
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" variant="primary" wire:click="trackIssue('{{ $item['key'] }}')" data-test="track-button">
                                            {{ __('Track') }}
                                        </flux:button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
