<?php

use App\Models\Project;
use App\Models\Repo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Repository')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Repo $repo;

    public string $name = '';

    public string $url = '';

    public string $primary_language = '';

    public string $default_branch = 'main';

    public string $tags = '';

    public function mount(): void
    {
        $this->name = $this->repo->name;
        $this->url = $this->repo->url;
        $this->primary_language = $this->repo->primary_language ?? '';
        $this->default_branch = $this->repo->default_branch ?? 'main';
        $this->tags = $this->repo->tags ? implode(', ', $this->repo->tags) : '';
    }

    public function updateRepo(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url', 'max:2048'],
            'primary_language' => ['nullable', 'string', 'max:255'],
            'default_branch' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:1000'],
        ]);

        $tagsArray = $validated['tags']
            ? array_map('trim', explode(',', $validated['tags']))
            : null;

        $this->repo->update([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'primary_language' => $validated['primary_language'] ?: null,
            'default_branch' => $validated['default_branch'] ?: 'main',
            'tags' => $tagsArray ? array_values(array_filter($tagsArray)) : null,
        ]);

        $this->redirect(route('projects.repos.show', [$this->project, $this->repo]), navigate: true);
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ __('Edit Repository') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Update repository ":name".', ['name' => $repo->name]) }}</flux:text>
    </div>

    <form wire:submit="updateRepo" class="max-w-xl space-y-6" data-test="edit-repo-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" data-test="repo-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('URL') }}</flux:label>
            <flux:input wire:model="url" type="url" data-test="repo-url-input" required />
            <flux:error name="url" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Primary Language') }}</flux:label>
            <flux:input wire:model="primary_language" data-test="repo-language-input" />
            <flux:error name="primary_language" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Default Branch') }}</flux:label>
            <flux:input wire:model="default_branch" data-test="repo-branch-input" />
            <flux:error name="default_branch" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Tags') }}</flux:label>
            <flux:input wire:model="tags" :placeholder="__('backend, api, frontend')" data-test="repo-tags-input" />
            <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Comma-separated list of tags') }}</flux:text>
            <flux:error name="tags" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-repo-button">{{ __('Update Repository') }}</flux:button>
            <a href="{{ route('projects.repos.show', [$project, $repo]) }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
