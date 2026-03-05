<?php

use App\Models\Milestone;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Milestone')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public Milestone $milestone;

    public string $title = '';

    public string $description = '';

    public string $status = 'open';

    public string $due_date = '';

    public function mount(): void
    {
        $this->title = $this->milestone->title;
        $this->description = $this->milestone->description ?? '';
        $this->status = $this->milestone->status ?? 'open';
        $this->due_date = $this->milestone->due_date?->format('Y-m-d') ?? '';
    }

    public function updateMilestone(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', 'in:open,active,closed'],
            'due_date' => ['nullable', 'date'],
        ]);

        $this->milestone->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'status' => $validated['status'],
            'due_date' => $validated['due_date'] ?: null,
        ]);

        $this->redirect(route('projects.milestones.show', [$this->project, $this->milestone]), navigate: true);
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
        <flux:heading size="xl">{{ __('Edit Milestone') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Update milestone ":title".', ['title' => $milestone->title]) }}</flux:text>
    </div>

    <form wire:submit="updateMilestone" class="max-w-xl space-y-6" data-test="edit-milestone-form">
        <flux:field>
            <flux:label>{{ __('Title') }}</flux:label>
            <flux:input wire:model="title" data-test="milestone-title-input" required />
            <flux:error name="title" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Description') }}</flux:label>
            <flux:textarea wire:model="description" data-test="milestone-description-input" rows="3" />
            <flux:error name="description" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Status') }}</flux:label>
            <flux:select wire:model="status" data-test="milestone-status-input">
                <flux:select.option value="open">{{ __('Open') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="closed">{{ __('Closed') }}</flux:select.option>
            </flux:select>
            <flux:error name="status" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Due Date') }}</flux:label>
            <flux:input wire:model="due_date" type="date" data-test="milestone-due-date-input" />
            <flux:error name="due_date" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-milestone-button">{{ __('Update Milestone') }}</flux:button>
            <a href="{{ route('projects.milestones.show', [$project, $milestone]) }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
