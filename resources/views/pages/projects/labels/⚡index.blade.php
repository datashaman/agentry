<?php

use App\Models\Label;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Labels')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public string $newName = '';

    public string $newColor = '#6366f1';

    public ?int $editingLabelId = null;

    public string $editName = '';

    public string $editColor = '';

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function labels(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->labels()
            ->withCount(['stories', 'bugs'])
            ->orderBy('name')
            ->get();
    }

    public function createLabel(): void
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newColor' => 'required|string|max:7',
        ]);

        $this->project->labels()->create([
            'name' => $this->newName,
            'color' => $this->newColor,
        ]);

        $this->reset('newName');
        $this->newColor = '#6366f1';
        unset($this->labels);
    }

    public function startEditing(int $labelId): void
    {
        $label = $this->project->labels()->findOrFail($labelId);
        $this->editingLabelId = $labelId;
        $this->editName = $label->name;
        $this->editColor = $label->color;
    }

    public function cancelEditing(): void
    {
        $this->editingLabelId = null;
        $this->reset('editName', 'editColor');
    }

    public function updateLabel(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editColor' => 'required|string|max:7',
        ]);

        $label = $this->project->labels()->findOrFail($this->editingLabelId);
        $label->update([
            'name' => $this->editName,
            'color' => $this->editColor,
        ]);

        $this->editingLabelId = null;
        $this->reset('editName', 'editColor');
        unset($this->labels);
    }

    public function deleteLabel(int $labelId): void
    {
        $label = $this->project->labels()->findOrFail($labelId);
        $label->delete();
        unset($this->labels);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div>
        <flux:heading size="xl">{{ __('Labels') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Manage labels for :project.', ['project' => $project->name]) }}</flux:text>
    </div>

    {{-- Inline Create Form --}}
    <form wire:submit="createLabel" class="flex items-end gap-3" data-test="create-label-form">
        <div class="flex-1">
            <flux:input wire:model="newName" label="{{ __('Name') }}" placeholder="{{ __('Label name') }}" data-test="new-label-name" />
        </div>
        <div>
            <flux:input type="color" wire:model="newColor" label="{{ __('Color') }}" data-test="new-label-color" />
        </div>
        <flux:button type="submit" variant="primary" data-test="create-label-button">{{ __('Add Label') }}</flux:button>
    </form>

    @if ($this->labels->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Labels') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No labels found for this project. Create one above.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Color') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Stories') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Bugs') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->labels as $label)
                        <tr class="border-b border-zinc-200 dark:border-zinc-700" data-test="label-row" wire:key="label-{{ $label->id }}">
                            @if ($editingLabelId === $label->id)
                                <td class="px-4 py-3">
                                    <input type="color" wire:model="editColor" class="h-8 w-8 cursor-pointer rounded border-0" data-test="edit-label-color" />
                                </td>
                                <td class="px-4 py-3">
                                    <flux:input wire:model="editName" size="sm" data-test="edit-label-name" />
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $label->stories_count }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $label->bugs_count }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <flux:button size="sm" variant="primary" wire:click="updateLabel" data-test="save-label-button">{{ __('Save') }}</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="cancelEditing" data-test="cancel-edit-button">{{ __('Cancel') }}</flux:button>
                                    </div>
                                </td>
                            @else
                                <td class="px-4 py-3">
                                    <div class="h-6 w-6 rounded-full" style="background-color: {{ $label->color }}" data-test="label-color"></div>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text class="font-medium text-zinc-900 dark:text-zinc-100" data-test="label-name">{{ $label->name }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text data-test="label-stories-count">{{ $label->stories_count }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text data-test="label-bugs-count">{{ $label->bugs_count }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="startEditing({{ $label->id }})" data-test="edit-label-button">{{ __('Edit') }}</flux:button>
                                        <flux:modal.trigger name="confirm-label-deletion-{{ $label->id }}">
                                            <flux:button size="sm" variant="danger" data-test="delete-label-button">{{ __('Delete') }}</flux:button>
                                        </flux:modal.trigger>
                                    </div>
                                </td>
                            @endif
                        </tr>

                        {{-- Delete Confirmation Modal --}}
                        <flux:modal name="confirm-label-deletion-{{ $label->id }}" focusable class="max-w-lg">
                            <div class="space-y-6">
                                <div>
                                    <flux:heading size="lg">{{ __('Are you sure you want to delete this label?') }}</flux:heading>
                                    <flux:text class="mt-2">{{ __('This action cannot be undone. The label ":name" will be permanently deleted.', ['name' => $label->name]) }}</flux:text>
                                </div>
                                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                                    <flux:modal.close>
                                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                                    </flux:modal.close>
                                    <flux:button variant="danger" wire:click="deleteLabel({{ $label->id }})" data-test="confirm-delete-label-button">
                                        {{ __('Delete Label') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:modal>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
