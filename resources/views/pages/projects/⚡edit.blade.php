<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Project')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public string $name = '';

    public string $description = '';

    public string $instructions = '';

    public function mount(): void
    {
        $this->name = $this->project->name;
        $this->description = $this->project->description ?? '';
        $this->instructions = $this->project->instructions ?? '';
    }

    public function updateProject(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->project->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'instructions' => $validated['instructions'] ?: null,
        ]);

        $this->redirect(route('projects.show', $this->project), navigate: true);
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
        <flux:heading size="xl">{{ __('Edit Project') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Update project ":name".', ['name' => $project->name]) }}</flux:text>
    </div>

    <form wire:submit="updateProject" class="max-w-xl space-y-6" data-test="edit-project-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" data-test="project-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Description') }}</flux:label>
            <flux:textarea wire:model="description" data-test="project-description-input" rows="3" />
            <flux:description>{{ __('A brief description of the project.') }}</flux:description>
            <flux:error name="description" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Instructions') }}</flux:label>
            <flux:textarea wire:model="instructions" data-test="project-instructions-input" rows="6" />
            <flux:description>{{ __('Instructions included at the start of agent conversations for this project.') }}</flux:description>
            <flux:error name="instructions" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-project-button">{{ __('Update Project') }}</flux:button>
            <a href="{{ route('projects.show', $project) }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
