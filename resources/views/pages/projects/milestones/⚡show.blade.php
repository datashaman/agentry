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

    public function deleteMilestone(): void
    {
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

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-milestone-deletion" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this milestone?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('This action cannot be undone. The milestone ":title" will be permanently deleted.', ['title' => $milestone->title]) }}</flux:text>
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteMilestone" data-test="confirm-delete-milestone-button">
                    {{ __('Delete Milestone') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
